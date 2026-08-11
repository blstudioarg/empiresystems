<?php

namespace App\Http\Controllers\Configuracion;

use App\Http\Controllers\Controller;
use App\Models\PosCuenta;
use App\Models\PosMesa;
use App\Models\PosZona;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * CRUD de mesas (feature 038), embebido en Configuración → POS. Mismo patrón y mismo criterio de
 * borrado que {@see PosZonaController}: una mesa con cuenta abierta no se elimina, y se dice por
 * qué (FR-015).
 */
class PosMesaController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $mesas = PosMesa::query()
            ->with('zona:id,nombre')
            ->when($request->filled('zona_id'), fn ($q) => $q->where('zona_id', $request->integer('zona_id')))
            ->orderBy('orden')
            ->orderBy('nombre')
            ->get();

        $ocupadas = PosCuenta::query()
            ->where('estado', PosCuenta::ESTADO_ABIERTA)
            ->whereNotNull('mesa_id')
            ->pluck('mesa_id')
            ->all();

        return response()->json([
            'data' => $mesas->map(fn (PosMesa $mesa) => [
                'id' => $mesa->id,
                'nombre' => $mesa->nombre,
                'zona_id' => $mesa->zona_id,
                'zona_nombre' => $mesa->zona?->nombre,
                'orden' => (int) $mesa->orden,
                'ocupada' => in_array($mesa->id, $ocupadas, true),
                'update_url' => route('configuracion.pos.mesas.update', ['mesa' => $mesa->id]),
                'delete_url' => route('configuracion.pos.mesas.destroy', ['mesa' => $mesa->id]),
            ])->values(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $mesa = PosMesa::create($this->validar($request) + ['tenant_id' => tenant()->getTenantKey()]);

        return response()->json(['message' => 'Mesa creada.', 'id' => $mesa->id], 201);
    }

    public function update(Request $request, string $mesa): JsonResponse
    {
        $modelo = PosMesa::query()->findOrFail($mesa);

        $modelo->update($this->validar($request, $modelo->id));

        return response()->json(['message' => 'Mesa actualizada.']);
    }

    public function destroy(string $mesa): JsonResponse
    {
        $modelo = PosMesa::query()->findOrFail($mesa);

        $abiertas = PosCuenta::query()
            ->where('mesa_id', $modelo->id)
            ->where('estado', PosCuenta::ESTADO_ABIERTA)
            ->count();

        if ($abiertas > 0) {
            return response()->json([
                'message' => "No se puede eliminar «{$modelo->nombre}»: tiene una cuenta abierta. Cóbrala o anúlala primero.",
            ], 422);
        }

        $modelo->delete();

        return response()->json(['message' => 'Mesa eliminada.']);
    }

    /** @return array<string, mixed> */
    private function validar(Request $request, ?int $ignorarId = null): array
    {
        $datos = $request->validate([
            'zona_id' => ['required', 'integer'],
            'nombre' => ['required', 'string', 'max:40'],
            'orden' => ['nullable', 'integer', 'min:0'],
        ]);

        // La zona se resuelve bajo el TenantScope: sin esto, un id de otro tenant pasaría la
        // validación `integer` y colgaría la mesa de una zona ajena.
        if (PosZona::query()->find($datos['zona_id']) === null) {
            throw ValidationException::withMessages(['zona_id' => 'La zona indicada no existe.']);
        }

        $request->validate([
            'nombre' => [
                Rule::unique('pos_mesas', 'nombre')
                    ->where('tenant_id', tenant()->getTenantKey())
                    ->where('zona_id', $datos['zona_id'])
                    ->whereNull('deleted_at')
                    ->ignore($ignorarId),
            ],
        ]);

        // Campo vacío → `null` (ConvertEmptyStringsToNull) → `nullable` lo deja pasar; la columna
        // es NOT NULL con default 0, así que hay que normalizar antes de guardar.
        $datos['orden'] ??= 0;

        return $datos;
    }
}
