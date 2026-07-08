<?php

namespace App\Http\Controllers;

use App\Models\CategoriaArticulo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CategoriaArticuloController extends Controller
{
    public function index(): JsonResponse
    {
        $categorias = CategoriaArticulo::orderBy('nombre')->get(['id', 'nombre']);

        return response()->json($categorias);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate($this->reglas());

        $categoria = CategoriaArticulo::create($validated);

        return response()->json([
            'message' => 'Categoría creada correctamente.',
            'categoria' => $categoria->only(['id', 'nombre']),
        ], 201);
    }

    public function update(Request $request, string $categoria): JsonResponse
    {
        // Sin binding implícito de ruta (mismo motivo que ArticuloController@update):
        // se resuelve manualmente, una vez el TenantScope ya está garantizado.
        $categoria = CategoriaArticulo::findOrFail($categoria);

        $validated = $request->validate($this->reglas($categoria->id));

        $categoria->update($validated);

        return response()->json([
            'message' => 'Categoría actualizada correctamente.',
            'categoria' => $categoria->only(['id', 'nombre']),
        ]);
    }

    public function destroy(string $categoria): JsonResponse
    {
        $categoria = CategoriaArticulo::findOrFail($categoria);

        $categoria->delete();

        return response()->json(['message' => 'Categoría eliminada correctamente.']);
    }

    /**
     * @return array<string, mixed>
     */
    private function reglas(?int $ignorarId = null): array
    {
        $unica = Rule::unique('categorias_articulo', 'nombre')
            ->where('tenant_id', tenant()->id);

        if ($ignorarId !== null) {
            $unica->ignore($ignorarId);
        }

        return [
            'nombre' => ['required', 'string', 'max:60', $unica],
        ];
    }
}
