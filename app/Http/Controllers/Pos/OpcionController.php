<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Models\Articulo;
use App\Models\PosOpcion;
use App\Models\PosOpcionGrupo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Opciones de artículo (feature 038, US3).
 *
 * `precio_defecto` es solo el punto de partida al asignar la opción a un artículo: el precio que
 * se cobra vive en el pivot y es propio de cada artículo (FR-039).
 */
class OpcionController extends Controller
{
    public function index(Request $request): View|JsonResponse
    {
        if (! $request->wantsJson()) {
            return view('pos.opciones.index', [
                'grupos' => PosOpcionGrupo::query()->orderBy('orden')->orderBy('nombre')->get(),
                'articulos' => Articulo::query()->orderBy('nombre')->get(['id', 'nombre']),
            ]);
        }

        $opciones = PosOpcion::query()
            ->with('grupo:id,nombre', 'articuloVinculado:id,nombre')
            ->withCount('articulos')
            ->orderBy('orden')
            ->orderBy('nombre')
            ->get();

        return response()->json([
            'data' => $opciones->map(fn (PosOpcion $opcion) => [
                'id' => $opcion->id,
                'nombre' => $opcion->nombre,
                'grupo_id' => $opcion->grupo_id,
                'grupo_nombre' => $opcion->grupo?->nombre,
                'precio_defecto' => number_format((float) $opcion->precio_defecto, 2, '.', ''),
                'articulo_vinculado_id' => $opcion->articulo_vinculado_id,
                'articulo_vinculado_nombre' => $opcion->articuloVinculado?->nombre,
                'orden' => (int) $opcion->orden,
                'articulos' => $opcion->articulos_count,
                'update_url' => route('pos.opciones.update', ['opcion' => $opcion->id]),
                'delete_url' => route('pos.opciones.destroy', ['opcion' => $opcion->id]),
            ])->values(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $opcion = PosOpcion::create($this->validar($request) + ['tenant_id' => tenant()->getTenantKey()]);

        return response()->json(['message' => 'Opción creada.', 'id' => $opcion->id], 201);
    }

    public function update(Request $request, string $opcion): JsonResponse
    {
        $modelo = PosOpcion::query()->findOrFail($opcion);

        // Cambiar `precio_defecto` NO toca el precio ya asignado en ningún artículo (FR-039): el
        // pivot es independiente a propósito, y por eso aquí no se propaga nada.
        $modelo->update($this->validar($request, $modelo->id));

        return response()->json(['message' => 'Opción actualizada.']);
    }

    public function destroy(string $opcion): JsonResponse
    {
        $modelo = PosOpcion::query()->withCount('articulos')->findOrFail($opcion);

        if ($modelo->articulos_count > 0) {
            return response()->json([
                'message' => "No se puede eliminar «{$modelo->nombre}»: se usa en {$modelo->articulos_count} artículo(s).",
            ], 422);
        }

        $modelo->delete();

        return response()->json(['message' => 'Opción eliminada.']);
    }

    /** @return array<string, mixed> */
    private function validar(Request $request, ?int $ignorarId = null): array
    {
        $datos = $request->validate([
            'grupo_id' => ['required', 'integer'],
            'nombre' => ['required', 'string', 'max:60'],
            'precio_defecto' => ['nullable', 'numeric', 'min:0'],
            'articulo_vinculado_id' => ['nullable', 'integer'],
            'orden' => ['nullable', 'integer', 'min:0'],
        ]);

        // `precio_defecto`/`orden` son NOT NULL con default: un campo vacío llega como `null`
        // (ConvertEmptyStringsToNull) y `nullable` lo deja pasar, pisando el default con un NULL
        // real. `articulo_vinculado_id` sí es nullable de verdad en la tabla: no se toca.
        $datos['precio_defecto'] ??= 0;
        $datos['orden'] ??= 0;

        // Grupo y artículo vinculado se resuelven bajo el TenantScope, no por `exists:` a secas:
        // un `exists` sin filtro de tenant aceptaría un id de otro tenant.
        if (PosOpcionGrupo::query()->find($datos['grupo_id']) === null) {
            throw ValidationException::withMessages(['grupo_id' => 'El grupo indicado no existe.']);
        }

        if (! empty($datos['articulo_vinculado_id']) && Articulo::query()->find($datos['articulo_vinculado_id']) === null) {
            throw ValidationException::withMessages(['articulo_vinculado_id' => 'El artículo vinculado no existe.']);
        }

        $request->validate([
            'nombre' => [
                Rule::unique('pos_opciones', 'nombre')
                    ->where('tenant_id', tenant()->getTenantKey())
                    ->where('grupo_id', $datos['grupo_id'])
                    ->whereNull('deleted_at')
                    ->ignore($ignorarId),
            ],
        ]);

        return $datos;
    }
}
