<?php

namespace App\Http\Controllers;

use App\Enums\AccionLogActividad;
use App\Ia\CatalogoTools;
use App\Ia\ConversacionAsistente;
use App\Services\AsistenteIa;
use App\Services\RegistradorActividad;
use App\Support\IaTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Chat del asistente IA (feature 030). Streaming SSE dentro del request (D6), sin colas/workers.
 */
class AsistenteChatController extends Controller
{
    public function __construct(
        private readonly ConversacionAsistente $conversacion,
        private readonly AsistenteIa $asistente,
        private readonly RegistradorActividad $registradorActividad,
    ) {}

    /**
     * POST /asistente/mensaje — respuesta por streaming SSE.
     */
    public function mensaje(Request $request): StreamedResponse|JsonResponse
    {
        $datos = $request->validate([
            'mensaje' => ['required', 'string', 'max:4000'],
        ]);

        if (! IaTenant::configurada()) {
            return response()->json(['error' => 'no_configurado'], 409);
        }

        $usuario = $request->user();
        $mensaje = $datos['mensaje'];

        $idPrevio = $this->conversacion->idActivo();

        $respuesta = new StreamedResponse(function () use ($request, $usuario, $mensaje, $idPrevio) {
            $emitir = function (string $evento, array $datos): void {
                echo 'event: '.$evento."\n";
                echo 'data: '.json_encode($datos, JSON_UNESCAPED_UNICODE)."\n\n";
                if (ob_get_level() > 0) {
                    @ob_flush();
                }
                flush();
            };

            $this->asistente->responder($usuario, $mensaje, [
                'texto' => fn (string $delta) => $emitir('texto', ['delta' => $delta]),
                'actividad' => fn (string $tool) => $emitir('actividad', ['tool' => $tool]),
                'compactando' => fn () => $emitir('compactando', []),
                'accionPendiente' => fn (array $accion) => $emitir('accion_pendiente', $accion),
                'error' => function (string $codigo, string $mensaje, ?string $detalle) use ($emitir) {
                    $payload = ['codigo' => $codigo, 'mensaje' => $mensaje];
                    if ($detalle !== null) {
                        $payload['detalle'] = $detalle;
                    }
                    $emitir('error', $payload);
                },
            ]);

            // La conversación puede haber nacido con este mismo mensaje (FR-008): el panel necesita
            // su id para el historial sin tener que recargar la lista.
            $nueva = $this->conversacion->activa();
            if ($nueva !== null && $idPrevio !== $nueva->id) {
                $emitir('conversacion', ['id' => $nueva->id, 'titulo' => $nueva->titulo]);
            }

            $emitir('fin', []);

            // `StartSession` ya guardó la sesión cuando este callback empieza a correr (se ejecuta
            // en `send()`, después del middleware), así que todo lo que el asistente escribió aquí
            // —los turnos de la conversación y la acción pendiente— vive solo en memoria y se
            // perdería al terminar el request: sin este guardado explícito, cada mensaje arranca
            // sin contexto y el endpoint de confirmación no encuentra la acción propuesta.
            $request->session()->save();
        });

        $respuesta->headers->set('Content-Type', 'text/event-stream');
        $respuesta->headers->set('Cache-Control', 'no-cache');
        $respuesta->headers->set('X-Accel-Buffering', 'no');

        return $respuesta;
    }

    /**
     * POST /asistente/accion/{id}/confirmar — única vía de escritura del asistente.
     */
    public function confirmar(Request $request, string $id): JsonResponse
    {
        $pendiente = $this->conversacion->accionPendiente();

        if ($pendiente === null || $pendiente['id'] !== $id) {
            return response()->json(['ok' => false, 'mensaje' => 'La acción ya no está disponible.'], 404);
        }

        $usuario = $request->user();

        $hechas = [];
        $rechazadas = [];
        $ultimaUrl = null;
        $fallosPorPermiso = 0;

        // Se ejecutan todas y se informa de las que fallaron, en vez de abortar el lote entero por
        // una: es el mismo criterio que ya aplican `ImportadorExcel` e `ImportadorLeads` ("nunca
        // aborta por filas inválidas; importa las válidas y reporta las rechazadas"). Si una de diez
        // altas trae un NIF repetido, perder las otras nueve sería peor que informar del rechazo.
        foreach ($pendiente['acciones'] as $accion) {
            $tool = CatalogoTools::resolver($accion['tool'], $usuario);

            if ($tool === null) {
                $fallosPorPermiso++;
                $rechazadas[] = $accion['resumen'].': ya no tenés permiso para esta acción.';

                continue;
            }

            try {
                $resultado = $tool->ejecutar($accion['parametros']);
            } catch (ValidationException $e) {
                // Rechazo legítimo (datos incompletos, NIF duplicado…). Se registra para poder
                // diagnosticarlo después: sin esto el fallo no dejaba rastro en ningún sitio.
                Log::warning('asistente.accion.rechazada', [
                    'tool' => $accion['tool'],
                    'motivo' => $e->getMessage(),
                    'parametros' => $accion['parametros'],
                    'user_id' => $usuario?->id,
                ]);

                $rechazadas[] = $accion['resumen'].': '.$e->getMessage();

                continue;
            } catch (\Throwable $e) {
                report($e);
                $rechazadas[] = $accion['resumen'].': no se pudo completar.';

                continue;
            }

            $hechas[] = $resultado['descripcion'] ?? $accion['resumen'];
            $ultimaUrl = $resultado['url'] ?? $accion['url'] ?? $ultimaUrl;

            $this->registradorActividad->registrar(
                $usuario,
                AccionLogActividad::Alta,
                null,
                $resultado['id'] ?? null,
                'Asistente IA: '.($resultado['descripcion'] ?? $accion['resumen']),
            );
        }

        $this->conversacion->limpiarAccion();

        // El modelo necesita saber qué salió y qué no, o en el turno siguiente da por hecho que se
        // hizo todo.
        $this->conversacion->agregarNotaInterna(
            'Resultado de la propuesta: '.count($hechas).' acción(es) ejecutada(s)'
            .($rechazadas === [] ? '.' : '; rechazadas: '.implode(' | ', $rechazadas))
        );

        if ($hechas === []) {
            // Perder el permiso es una condición distinta de que los datos no valgan, y merece su
            // propio código: 403 solo si TODO el lote se cayó por eso.
            $codigo = $fallosPorPermiso === count($pendiente['acciones']) ? 403 : 422;

            return response()->json([
                'ok' => false,
                'mensaje' => $codigo === 403
                    ? 'Ya no tenés permiso para esta acción.'
                    : ($rechazadas[0] ?? 'No se pudo completar la acción.'),
                'rechazadas' => $rechazadas,
            ], $codigo);
        }

        $mensaje = count($hechas) === 1
            ? ($hechas[0].' — hecho.')
            : count($hechas).' acciones completadas.';

        if ($rechazadas !== []) {
            $mensaje .= ' '.count($rechazadas).' no se pudieron completar.';
        }

        return response()->json([
            'ok' => true,
            'mensaje' => $mensaje,
            'hechas' => $hechas,
            'rechazadas' => $rechazadas,
            // Solo se ofrece "Ver" cuando hay una única acción: con varias, a cuál llevaría.
            'url' => count($hechas) === 1 ? $ultimaUrl : null,
        ]);
    }

    /**
     * POST /asistente/accion/{id}/cancelar.
     */
    public function cancelar(Request $request, string $id): JsonResponse
    {
        $pendiente = $this->conversacion->accionPendiente();

        if ($pendiente !== null && $pendiente['id'] === $id) {
            $this->conversacion->limpiarAccion();
            $this->conversacion->agregarNotaInterna('El usuario canceló la acción propuesta.');
        }

        return response()->json(['ok' => true]);
    }
}
