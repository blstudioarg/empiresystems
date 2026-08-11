<?php

namespace App\Http\Controllers\Configuracion;

use App\Http\Controllers\Controller;
use App\Models\PosZona;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * CRUD de zonas de sala (feature 038), embebido en Configuración → POS. Patrón "alta/edición en
 * modal + AJAX": solo `index/store/update/destroy`, sin `create`/`edit`.
 *
 * `destroy` responde **422 con mensaje legible** cuando hay mesas colgando, en vez de dejar
 * reventar la foreign key con un error de base de datos que el usuario no puede entender.
 */
class PosZonaController extends Controller
{
    public function index(): JsonResponse
    {
        $zonas = PosZona::query()
            ->withCount('mesas')
            ->orderBy('orden')
            ->orderBy('nombre')
            ->get();

        return response()->json([
            'data' => $zonas->map(fn (PosZona $zona) => [
                'id' => $zona->id,
                'nombre' => $zona->nombre,
                'suplemento_porcentaje' => number_format((float) $zona->suplemento_porcentaje, 2, '.', ''),
                'orden' => (int) $zona->orden,
                'mesas' => $zona->mesas_count,
                'update_url' => route('configuracion.pos.zonas.update', ['zona' => $zona->id]),
                'delete_url' => route('configuracion.pos.zonas.destroy', ['zona' => $zona->id]),
            ])->values(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $zona = PosZona::create($this->validar($request) + ['tenant_id' => tenant()->getTenantKey()]);

        return response()->json(['message' => 'Zona creada.', 'id' => $zona->id], 201);
    }

    public function update(Request $request, string $zona): JsonResponse
    {
        $modelo = PosZona::query()->findOrFail($zona);

        $modelo->update($this->validar($request, $modelo->id));

        return response()->json(['message' => 'Zona actualizada.']);
    }

    public function destroy(string $zona): JsonResponse
    {
        $modelo = PosZona::query()->withCount('mesas')->findOrFail($zona);

        if ($modelo->mesas_count > 0) {
            return response()->json([
                'message' => "No se puede eliminar «{$modelo->nombre}»: tiene {$modelo->mesas_count} mesa(s). Muévelas o elimínalas primero.",
            ], 422);
        }

        $modelo->delete();

        return response()->json(['message' => 'Zona eliminada.']);
    }

    /** @return array<string, mixed> */
    private function validar(Request $request, ?int $ignorarId = null): array
    {
        $datos = $request->validate([
            'nombre' => [
                'required', 'string', 'max:60',
                Rule::unique('pos_zonas', 'nombre')
                    ->where('tenant_id', tenant()->getTenantKey())
                    ->whereNull('deleted_at')
                    ->ignore($ignorarId),
            ],
            'suplemento_porcentaje' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'orden' => ['nullable', 'integer', 'min:0'],
        ]);

        // El campo de suplemento va oculto (`d-none`) cuando la capacidad está apagada: el
        // navegador igual envía el input vacío, que `ConvertEmptyStringsToNull` convierte en
        // `null` antes de llegar aquí. `nullable` deja pasar ese `null` literal, que pisaría el
        // default `0.00` de la columna con un NULL real y violaría el NOT NULL.
        $datos['suplemento_porcentaje'] ??= 0;
        $datos['orden'] ??= 0;

        return $datos;
    }
}
