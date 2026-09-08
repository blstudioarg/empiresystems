<?php

namespace App\Http\Controllers;

use App\Ia\CatalogoTools;
use App\Ia\ConversacionAsistente;
use App\Models\AsistenteConversacion;
use App\Models\AsistenteMensaje;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Historial de conversaciones del asistente (feature 045, contracts/endpoints.md).
 *
 * Todo id se resuelve por `ConversacionAsistente::buscarPropia()`, que acota al tenant activo **y**
 * a la persona autenticada. Un id ajeno responde **404 y no 403**: un 403 confirmaría que ese hilo
 * existe, y eso ya es una filtración (Principio I).
 */
class AsistenteConversacionController extends Controller
{
    /** Tope del listado: con 90 días de retención el volumen por persona queda muy por debajo. */
    private const TOPE_LISTADO = 50;

    public function __construct(private readonly ConversacionAsistente $conversacion) {}

    /**
     * GET /asistente/conversaciones
     */
    public function index(Request $request): JsonResponse
    {
        $usuario = $request->user();
        $activaId = $this->conversacion->idActivo();

        $conversaciones = AsistenteConversacion::query()
            ->paraUsuario($usuario)
            // Una conversación entra en el historial cuando la persona escribió algo (FR-008):
            // abrir el panel y no escribir no debe dejar un hilo en blanco.
            ->whereHas('mensajes', fn ($q) => $q->where('rol', AsistenteMensaje::ROL_USER))
            ->orderByDesc('ultima_actividad_en')
            ->limit(self::TOPE_LISTADO)
            ->get();

        return response()->json([
            'conversaciones' => $conversaciones->map(fn (AsistenteConversacion $c) => [
                'id' => $c->id,
                'titulo' => $c->titulo,
                'ultima_actividad_en' => $c->ultima_actividad_en?->toIso8601String(),
                'activa' => $c->id === $activaId,
            ])->all(),
        ]);
    }

    /**
     * POST /asistente/conversaciones — conversación nueva.
     *
     * No crea fila: la conversación nace con el primer mensaje de la persona (FR-008). Aquí solo se
     * olvida la activa y se descarta la propuesta pendiente (FR-023).
     */
    public function store(): JsonResponse
    {
        $this->conversacion->reiniciar();

        return response()->json(['ok' => true, 'conversacion_id' => null]);
    }

    /**
     * GET /asistente/conversaciones/{id} — abrir una conversación anterior.
     */
    public function show(string $id): JsonResponse
    {
        $conversacion = $this->conversacion->buscarPropia((int) $id);

        if ($conversacion === null) {
            return response()->json(['mensaje' => 'La conversación no existe.'], 404);
        }

        // Cambiar de hilo invalida cualquier propuesta pendiente del anterior (FR-023). Se activa
        // primero para que `limpiarAccion` no dependa del orden.
        $this->conversacion->activar($conversacion);
        $this->conversacion->limpiarAccion();

        return response()->json([
            'id' => $conversacion->id,
            'titulo' => $conversacion->titulo,
            'resumida' => $conversacion->estaCompactada(),
            'mensajes' => $this->paraPanel($conversacion),
        ]);
    }

    /**
     * DELETE /asistente/conversaciones/{id}
     */
    public function destroy(string $id): JsonResponse
    {
        $conversacion = $this->conversacion->buscarPropia((int) $id);

        if ($conversacion === null) {
            return response()->json(['ok' => false, 'mensaje' => 'La conversación no existe.'], 404);
        }

        $eraLaActiva = $conversacion->id === $this->conversacion->idActivo();

        // Borrado definitivo, sin softDeletes: lo que la persona pide eliminar se elimina (FR-020).
        $conversacion->mensajes()->delete();
        $conversacion->delete();

        if ($eraLaActiva) {
            $this->conversacion->reiniciar();
        }

        return response()->json(['ok' => true, 'mensaje' => 'Conversación eliminada.']);
    }

    /**
     * Mensajes tal como los pinta el panel.
     *
     * Los turnos de rol `tool` no salen crudos: se traducen al pseudo-rol `actividad` con solo el
     * nombre de la herramienta. El resultado de una consulta puede traer datos de negocio que no
     * hacen falta en el cliente, y minimizar lo que viaja es parte del Principio II.
     *
     * @return array<int, array<string, string|null>>
     */
    private function paraPanel(AsistenteConversacion $conversacion): array
    {
        return $conversacion->mensajes->map(function (AsistenteMensaje $m) {
            if ($m->esInterno()) {
                return null; // nota del sistema para el modelo, no un mensaje de la persona
            }

            if ($m->rol === AsistenteMensaje::ROL_TOOL) {
                return null; // el nombre de la tool lo aporta el assistant que la pidió
            }

            if ($m->rol === AsistenteMensaje::ROL_ASSISTANT && ($m->metadatos['tool_calls'] ?? null)) {
                // Solo las herramientas de LECTURA se muestran como actividad. Una de escritura no
                // "consulta" nada: produjo una propuesta que el usuario confirmó o descartó, y esa
                // tarjeta no se persiste. Etiquetarla como consulta le mentía al usuario —y se veía
                // feo de verdad cuando el modelo pedía diez altas de golpe y salían nueve seguidas.
                $nombres = collect($m->metadatos['tool_calls'])
                    ->map(fn (array $tc) => $tc['function']['name'] ?? null)
                    ->filter()
                    ->filter(function (string $nombre) {
                        $tool = CatalogoTools::resolver($nombre, auth()->user());

                        return $tool !== null && $tool->esLectura();
                    })
                    ->unique()
                    ->values();

                if ($nombres->isEmpty()) {
                    return null;
                }

                return ['rol' => 'actividad', 'contenido' => $nombres->implode(', ')];
            }

            if ($m->contenido === null || $m->contenido === '') {
                return null;
            }

            return ['rol' => $m->rol, 'contenido' => $m->contenido];
        })->filter()->values()->all();
    }
}
