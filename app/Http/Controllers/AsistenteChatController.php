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

        $respuesta = new StreamedResponse(function () use ($usuario, $mensaje) {
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
                'accionPendiente' => fn (array $accion) => $emitir('accion_pendiente', $accion),
                'error' => function (string $codigo, string $mensaje, ?string $detalle) use ($emitir) {
                    $payload = ['codigo' => $codigo, 'mensaje' => $mensaje];
                    if ($detalle !== null) {
                        $payload['detalle'] = $detalle;
                    }
                    $emitir('error', $payload);
                },
            ]);

            $emitir('fin', []);
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
        $tool = CatalogoTools::resolver($pendiente['tool'], $usuario);

        if ($tool === null) {
            $this->conversacion->limpiarAccion();

            return response()->json(['ok' => false, 'mensaje' => 'Ya no tenés permiso para esta acción.'], 403);
        }

        try {
            $resultado = $tool->ejecutar($pendiente['parametros']);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['ok' => false, 'mensaje' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['ok' => false, 'mensaje' => 'No se pudo completar la acción.'], 422);
        }

        $this->conversacion->limpiarAccion();

        $this->registradorActividad->registrar(
            $usuario,
            AccionLogActividad::Alta,
            null,
            $resultado['id'] ?? null,
            'Asistente IA: '.($resultado['descripcion'] ?? $pendiente['resumen']),
        );

        // Informar el resultado al modelo para el próximo turno.
        $this->conversacion->agregar('user', 'La acción "'.$pendiente['resumen'].'" fue confirmada y ejecutada correctamente.');

        return response()->json([
            'ok' => true,
            'mensaje' => $resultado['mensaje'] ?? 'Acción realizada correctamente.',
            'url' => $resultado['url'] ?? $pendiente['url'] ?? null,
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
            $this->conversacion->agregar('user', 'El usuario canceló la acción propuesta.');
        }

        return response()->json(['ok' => true]);
    }

    /**
     * POST /asistente/reiniciar — conversación nueva.
     */
    public function reiniciar(): JsonResponse
    {
        $this->conversacion->reiniciar();

        return response()->json(['ok' => true]);
    }
}
