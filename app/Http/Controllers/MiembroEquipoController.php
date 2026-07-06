<?php

namespace App\Http\Controllers;

use App\Http\Requests\MiembroEquipoRequest;
use App\Models\MiembroEquipo;
use App\Models\User;
use App\Support\Haversine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MiembroEquipoController extends Controller
{
    public function index(Request $request): View|JsonResponse
    {
        $miembros = MiembroEquipo::where('tenant_id', tenant()->getTenantKey())
            ->with('user')
            ->orderBy('id')
            ->get();

        if ($request->wantsJson()) {
            return response()->json([
                'data' => $miembros->map(fn (MiembroEquipo $miembro) => [
                    'id' => $miembro->id,
                    'user_id' => $miembro->user_id,
                    'nombre' => $miembro->user->name,
                    'puesto' => $miembro->puesto,
                    'trabajo_direccion' => $miembro->trabajo_direccion,
                    'trabajo_latitud' => $miembro->trabajo_latitud !== null ? (float) $miembro->trabajo_latitud : null,
                    'trabajo_longitud' => $miembro->trabajo_longitud !== null ? (float) $miembro->trabajo_longitud : null,
                    'distancia_max_metros' => $miembro->distancia_max_metros,
                    'casa_direccion' => $miembro->casa_direccion,
                    'casa_latitud' => $miembro->casa_latitud !== null ? (float) $miembro->casa_latitud : null,
                    'casa_longitud' => $miembro->casa_longitud !== null ? (float) $miembro->casa_longitud : null,
                    'distancia_casa_trabajo_metros' => $miembro->distancia_casa_trabajo_metros,
                    'activo' => $miembro->activo,
                    'update_url' => route('miembros-equipo.update', $miembro),
                    'delete_url' => route('miembros-equipo.destroy', $miembro),
                ])->values(),
                'totales' => [
                    'total' => $miembros->count(),
                    'activos' => $miembros->where('activo', true)->count(),
                    'con_ubicacion' => $miembros->filter(fn (MiembroEquipo $miembro) => $miembro->tieneUbicacionTrabajo())->count(),
                ],
                'usuarios' => User::where('tenant_id', tenant()->getTenantKey())
                    ->orderBy('name')
                    ->get(['id', 'name', 'email'])
                    ->values(),
            ]);
        }

        return view('miembros-equipo.index');
    }

    public function store(MiembroEquipoRequest $request): RedirectResponse|JsonResponse
    {
        $datos = $this->conDistanciaCasaTrabajo($request->validated());
        $datos['tenant_id'] = tenant()->getTenantKey();

        MiembroEquipo::create($datos);

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Miembro creado correctamente.'], 201);
        }

        return redirect()->route('miembros-equipo.index')->with('success', 'Miembro creado correctamente.');
    }

    public function update(MiembroEquipoRequest $request, string $miembro): RedirectResponse|JsonResponse
    {
        $miembroModel = MiembroEquipo::where('tenant_id', tenant()->getTenantKey())->findOrFail($miembro);

        $miembroModel->update($this->conDistanciaCasaTrabajo($request->validated()));

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Miembro actualizado correctamente.']);
        }

        return redirect()->route('miembros-equipo.index')->with('success', 'Miembro actualizado correctamente.');
    }

    public function destroy(Request $request, string $miembro): RedirectResponse|JsonResponse
    {
        $miembroModel = MiembroEquipo::where('tenant_id', tenant()->getTenantKey())->findOrFail($miembro);

        $miembroModel->update(['activo' => false, 'dado_baja_at' => now()]);
        $miembroModel->delete();

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Miembro dado de baja correctamente.']);
        }

        return redirect()->route('miembros-equipo.index')->with('success', 'Miembro dado de baja correctamente.');
    }

    /**
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     */
    private function conDistanciaCasaTrabajo(array $datos): array
    {
        $tieneAmbas = isset($datos['trabajo_latitud'], $datos['trabajo_longitud'], $datos['casa_latitud'], $datos['casa_longitud']);

        $datos['distancia_casa_trabajo_metros'] = $tieneAmbas
            ? Haversine::metros(
                (float) $datos['casa_latitud'],
                (float) $datos['casa_longitud'],
                (float) $datos['trabajo_latitud'],
                (float) $datos['trabajo_longitud'],
            )
            : null;

        return $datos;
    }
}
