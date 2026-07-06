<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePlantillaEmailRequest;
use App\Http\Requests\UpdatePlantillaEmailRequest;
use App\Models\PlantillaEmail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PlantillaEmailController extends Controller
{
    public function index(Request $request): View|JsonResponse
    {
        if ($request->wantsJson()) {
            $plantillas = PlantillaEmail::query()->orderBy('titulo')->get();

            return response()->json([
                'data' => $plantillas->map(fn (PlantillaEmail $plantilla) => [
                    'id' => $plantilla->id,
                    'titulo' => $plantilla->titulo,
                    'asunto' => $plantilla->asunto,
                    'cuerpo' => $plantilla->cuerpo,
                    'activa' => $plantilla->activa,
                    'activa_label' => $plantilla->activa ? 'Activa' : 'Inactiva',
                    'modificado' => $plantilla->updated_at?->enZonaTenant()?->format('d/m/Y H:i'),
                    'update_url' => route('plantillas-email.update', $plantilla),
                    'delete_url' => route('plantillas-email.destroy', $plantilla),
                ])->values(),
                'totales' => [
                    'total' => $plantillas->count(),
                    'activas' => $plantillas->where('activa', true)->count(),
                    'inactivas' => $plantillas->where('activa', false)->count(),
                ],
            ]);
        }

        return view('plantillas-email.index');
    }

    public function store(StorePlantillaEmailRequest $request): RedirectResponse|JsonResponse
    {
        PlantillaEmail::create($request->validated());

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Plantilla creada correctamente.'], 201);
        }

        return redirect()->route('plantillas-email.index')->with('success', 'Plantilla creada correctamente.');
    }

    public function update(UpdatePlantillaEmailRequest $request, string $plantilla): RedirectResponse|JsonResponse
    {
        // Resolución manual del modelo (sin binding implícito) para garantizar el TenantScope.
        // Ver memoria project_tenant_route_binding.
        $plantilla = PlantillaEmail::findOrFail($plantilla);

        $plantilla->update($request->validated());

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Plantilla actualizada correctamente.']);
        }

        return redirect()->route('plantillas-email.index')->with('success', 'Plantilla actualizada correctamente.');
    }

    public function destroy(Request $request, string $plantilla): RedirectResponse|JsonResponse
    {
        $plantilla = PlantillaEmail::findOrFail($plantilla);

        $plantilla->delete();

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Plantilla eliminada correctamente.']);
        }

        return redirect()->route('plantillas-email.index')->with('success', 'Plantilla eliminada correctamente.');
    }
}
