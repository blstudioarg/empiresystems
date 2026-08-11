<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Models\Articulo;
use App\Models\PosOpcion;
use App\Models\PosOpcionGrupo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Asignación de grupos y opciones a un artículo (feature 038, US3).
 *
 * Es el "sub-listado editable embebido en un modal de edición" de las guías de front: la lista de
 * opciones asignadas con su precio y orden no cabe en atributos `data-*` de un botón, así que se
 * pide por un endpoint aparte en vez de inflar el HTML del listado.
 */
class ArticuloOpcionController extends Controller
{
    public function index(string $articulo): JsonResponse
    {
        $modelo = Articulo::query()->with(['posGrupos', 'posOpciones.grupo:id,nombre'])->findOrFail($articulo);

        return response()->json([
            'articulo_id' => $modelo->id,
            'grupos' => $modelo->posGrupos->map(fn (PosOpcionGrupo $grupo) => [
                'grupo_id' => $grupo->id,
                'nombre' => $grupo->nombre,
                'obligatorio' => (bool) $grupo->obligatorio,
                'min_selecciones' => (int) $grupo->min_selecciones,
                'max_selecciones' => $grupo->max_selecciones !== null ? (int) $grupo->max_selecciones : null,
                'orden' => (int) $grupo->pivot->orden,
            ])->values(),
            'opciones' => $modelo->posOpciones->map(fn (PosOpcion $opcion) => [
                'opcion_id' => $opcion->id,
                'nombre' => $opcion->nombre,
                'grupo_id' => $opcion->grupo_id,
                'grupo_nombre' => $opcion->grupo?->nombre,
                'precio' => number_format((float) $opcion->pivot->precio, 2, '.', ''),
                'precio_defecto' => number_format((float) $opcion->precio_defecto, 2, '.', ''),
                'orden' => (int) $opcion->pivot->orden,
            ])->values(),
            // Catálogo completo para el selector del modal.
            'disponibles' => [
                'grupos' => PosOpcionGrupo::query()->orderBy('nombre')->get(['id', 'nombre'])
                    ->map(fn ($g) => ['id' => $g->id, 'nombre' => $g->nombre])->values(),
                'opciones' => PosOpcion::query()->with('grupo:id,nombre')->orderBy('nombre')->get()
                    ->map(fn (PosOpcion $o) => [
                        'id' => $o->id,
                        'nombre' => $o->nombre,
                        'grupo_id' => $o->grupo_id,
                        'grupo_nombre' => $o->grupo?->nombre,
                        'precio_defecto' => number_format((float) $o->precio_defecto, 2, '.', ''),
                    ])->values(),
            ],
        ]);
    }

    /**
     * Recibe la lista COMPLETA de lo asignado y la sincroniza. Se descarta en silencio cualquier
     * id que no exista en el tenant: el servidor no confía en lo que llega del cliente.
     */
    public function sync(Request $request, string $articulo): JsonResponse
    {
        $modelo = Articulo::query()->findOrFail($articulo);

        $datos = $request->validate([
            'grupos' => ['present', 'array'],
            'grupos.*.grupo_id' => ['required', 'integer'],
            'grupos.*.orden' => ['nullable', 'integer', 'min:0'],
            'opciones' => ['present', 'array'],
            'opciones.*.opcion_id' => ['required', 'integer'],
            'opciones.*.precio' => ['required', 'numeric', 'min:0'],
            'opciones.*.orden' => ['nullable', 'integer', 'min:0'],
        ]);

        $gruposValidos = PosOpcionGrupo::query()
            ->whereIn('id', array_column($datos['grupos'], 'grupo_id'))
            ->pluck('id')->all();

        $opcionesValidas = PosOpcion::query()
            ->whereIn('id', array_column($datos['opciones'], 'opcion_id'))
            ->pluck('id')->all();

        $tenantId = tenant()->getTenantKey();

        $gruposSync = [];
        foreach ($datos['grupos'] as $orden => $grupo) {
            if (in_array((int) $grupo['grupo_id'], $gruposValidos, true)) {
                $gruposSync[(int) $grupo['grupo_id']] = [
                    'tenant_id' => $tenantId,
                    'orden' => $grupo['orden'] ?? $orden,
                ];
            }
        }

        $opcionesSync = [];
        foreach ($datos['opciones'] as $orden => $opcion) {
            if (in_array((int) $opcion['opcion_id'], $opcionesValidas, true)) {
                $opcionesSync[(int) $opcion['opcion_id']] = [
                    'tenant_id' => $tenantId,
                    'precio' => round((float) $opcion['precio'], 2),
                    'orden' => $opcion['orden'] ?? $orden,
                ];
            }
        }

        DB::transaction(function () use ($modelo, $gruposSync, $opcionesSync) {
            $modelo->posGrupos()->sync($gruposSync);
            $modelo->posOpciones()->sync($opcionesSync);
        });

        return response()->json(['message' => 'Opciones del artículo guardadas.']);
    }
}
