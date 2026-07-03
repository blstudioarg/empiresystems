<?php

namespace App\Http\Controllers;

use App\Models\Unidad;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UnidadController extends Controller
{
    public function index(): JsonResponse
    {
        $unidades = Unidad::orderBy('nombre')->get(['id', 'nombre']);

        return response()->json($unidades);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate($this->reglas());

        $unidad = Unidad::create($validated);

        return response()->json([
            'message' => 'Unidad creada correctamente.',
            'unidad' => $unidad->only(['id', 'nombre']),
        ], 201);
    }

    public function update(Request $request, string $unidad): JsonResponse
    {
        // Sin binding implícito de ruta (mismo motivo que ArticuloController@update):
        // se resuelve manualmente, una vez el TenantScope ya está garantizado.
        $unidad = Unidad::findOrFail($unidad);

        $validated = $request->validate($this->reglas($unidad->id));

        $unidad->update($validated);

        return response()->json([
            'message' => 'Unidad actualizada correctamente.',
            'unidad' => $unidad->only(['id', 'nombre']),
        ]);
    }

    public function destroy(string $unidad): JsonResponse
    {
        $unidad = Unidad::findOrFail($unidad);

        $unidad->delete();

        return response()->json(['message' => 'Unidad eliminada correctamente.']);
    }

    /**
     * @return array<string, mixed>
     */
    private function reglas(?int $ignorarId = null): array
    {
        $unica = Rule::unique('unidades', 'nombre')
            ->where('tenant_id', tenant()->id);

        if ($ignorarId !== null) {
            $unica->ignore($ignorarId);
        }

        return [
            'nombre' => ['required', 'string', 'max:20', $unica],
        ];
    }
}
