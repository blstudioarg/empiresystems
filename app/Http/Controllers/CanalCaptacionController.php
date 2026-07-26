<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCanalCaptacionRequest;
use App\Models\CanalCaptacion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CanalCaptacionController extends Controller
{
    /**
     * `?solo_activos=1` filtra los desactivados (selector de alta/importación de leads, FR-013);
     * sin el parámetro devuelve el catálogo completo (gestión del catálogo, filtros de informes).
     */
    public function index(Request $request): JsonResponse
    {
        $canales = CanalCaptacion::query()
            ->when($request->boolean('solo_activos'), fn ($q) => $q->where('activo', true))
            ->orderBy('orden')->orderBy('nombre')
            ->get(['id', 'nombre', 'activo', 'orden']);

        return response()->json(['data' => $canales]);
    }

    public function store(StoreCanalCaptacionRequest $request): JsonResponse
    {
        $canal = CanalCaptacion::create($request->validated());

        return response()->json([
            'message' => 'Canal de captación creado correctamente.',
            'canal' => $canal,
        ], 201);
    }

    public function update(StoreCanalCaptacionRequest $request, string $canal): JsonResponse
    {
        // Resolución manual (sin binding implícito) para garantizar el TenantScope activo.
        $canal = CanalCaptacion::findOrFail($canal);

        $canal->update($request->validated());

        return response()->json(['message' => 'Canal de captación actualizado correctamente.', 'canal' => $canal]);
    }

    /**
     * Borra el canal si no tiene leads asociados; si los tiene, lo desactiva en su lugar y lo
     * informa (FR-013): el histórico de leads con este canal no puede quedar huérfano.
     */
    public function destroy(string $canal): JsonResponse
    {
        $canal = CanalCaptacion::findOrFail($canal);

        if ($canal->leads()->exists()) {
            $canal->update(['activo' => false]);

            return response()->json([
                'message' => 'El canal tiene leads asociados: se desactivó en vez de eliminarse, conservando el histórico.',
                'desactivado' => true,
            ]);
        }

        $canal->delete();

        return response()->json(['message' => 'Canal de captación eliminado correctamente.', 'desactivado' => false]);
    }
}
