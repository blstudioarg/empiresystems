<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Models\PosOpcionGrupo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Grupos de opciones (feature 038, US3). Ej.: "Punto de cocción", "Guarnición".
 *
 * Dos reglas propias (FR-037): `min ≤ max` cuando hay máximo, y un grupo **obligatorio no puede
 * quedarse sin opciones** — si lo permitiéramos, el camarero se encontraría con un plato
 * imposible de comandar en pleno servicio.
 */
class OpcionGrupoController extends Controller
{
    public function index(): JsonResponse
    {
        $grupos = PosOpcionGrupo::query()
            ->withCount(['opciones', 'articulos'])
            ->orderBy('orden')
            ->orderBy('nombre')
            ->get();

        return response()->json([
            'data' => $grupos->map(fn (PosOpcionGrupo $grupo) => [
                'id' => $grupo->id,
                'nombre' => $grupo->nombre,
                'min_selecciones' => (int) $grupo->min_selecciones,
                'max_selecciones' => $grupo->max_selecciones !== null ? (int) $grupo->max_selecciones : null,
                'obligatorio' => (bool) $grupo->obligatorio,
                'orden' => (int) $grupo->orden,
                'opciones' => $grupo->opciones_count,
                'articulos' => $grupo->articulos_count,
                'update_url' => route('pos.opcion-grupos.update', ['grupo' => $grupo->id]),
                'delete_url' => route('pos.opcion-grupos.destroy', ['grupo' => $grupo->id]),
            ])->values(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $grupo = PosOpcionGrupo::create($this->validar($request) + ['tenant_id' => tenant()->getTenantKey()]);

        return response()->json(['message' => 'Grupo creado.', 'id' => $grupo->id], 201);
    }

    public function update(Request $request, string $grupo): JsonResponse
    {
        $modelo = PosOpcionGrupo::query()->withCount('opciones')->findOrFail($grupo);

        $datos = $this->validar($request, $modelo->id);

        if (($datos['obligatorio'] ?? false) && $modelo->opciones_count === 0) {
            throw ValidationException::withMessages([
                'obligatorio' => 'Un grupo obligatorio necesita al menos una opción: si no, el artículo no se podría comandar.',
            ]);
        }

        $modelo->update($datos);

        return response()->json(['message' => 'Grupo actualizado.']);
    }

    public function destroy(string $grupo): JsonResponse
    {
        $modelo = PosOpcionGrupo::query()->withCount('articulos')->findOrFail($grupo);

        if ($modelo->articulos_count > 0) {
            return response()->json([
                'message' => "No se puede eliminar «{$modelo->nombre}»: se usa en {$modelo->articulos_count} artículo(s).",
            ], 422);
        }

        $modelo->delete();

        return response()->json(['message' => 'Grupo eliminado.']);
    }

    /** @return array<string, mixed> */
    private function validar(Request $request, ?int $ignorarId = null): array
    {
        $datos = $request->validate([
            'nombre' => [
                'required', 'string', 'max:60',
                Rule::unique('pos_opcion_grupos', 'nombre')
                    ->where('tenant_id', tenant()->getTenantKey())
                    ->whereNull('deleted_at')
                    ->ignore($ignorarId),
            ],
            'min_selecciones' => ['nullable', 'integer', 'min:0', 'max:255'],
            'max_selecciones' => ['nullable', 'integer', 'min:1', 'max:255'],
            'obligatorio' => ['nullable', 'boolean'],
            'orden' => ['nullable', 'integer', 'min:0'],
        ]);

        // Los campos van `nullable` porque el formulario los envía vacíos si el usuario no los
        // toca; `ConvertEmptyStringsToNull` los convierte en `null` antes de llegar aquí, y esas
        // columnas son NOT NULL con default — sin normalizar, `create()` insertaría un NULL
        // literal que pisa el default y revienta la constraint.
        $datos['min_selecciones'] = (int) ($datos['min_selecciones'] ?? 0);
        $datos['orden'] ??= 0;
        $min = $datos['min_selecciones'];
        $max = $datos['max_selecciones'] ?? null;

        if ($max !== null && $min > (int) $max) {
            throw ValidationException::withMessages([
                'max_selecciones' => 'El máximo de selecciones no puede ser menor que el mínimo.',
            ]);
        }

        // `obligatorio` es el atajo de `min ≥ 1`: se mantienen coherentes para que la interfaz y
        // el validador de venta no puedan contradecirse.
        if (! empty($datos['obligatorio']) && $min < 1) {
            $datos['min_selecciones'] = 1;
        }

        if (empty($datos['obligatorio'])) {
            $datos['obligatorio'] = $min >= 1;
        }

        return $datos;
    }
}
