<?php

namespace App\Http\Controllers;

use App\Exceptions\AsignacionHorarioSolapadaException;
use App\Http\Requests\AsignacionHorarioRequest;
use App\Models\AsignacionHorario;
use App\Models\Horario;
use App\Models\MiembroEquipo;
use App\Support\AsignadorHorario;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AsignacionHorarioController extends Controller
{
    public function __construct(private readonly AsignadorHorario $asignadorHorario) {}

    public function index(Request $request, string $miembro): JsonResponse
    {
        $miembroModel = MiembroEquipo::where('tenant_id', tenant()->getTenantKey())->findOrFail($miembro);

        $asignaciones = AsignacionHorario::where('miembro_equipo_id', $miembroModel->id)
            ->with('horario')
            ->orderByDesc('vigente_desde')
            ->get();

        return response()->json([
            'data' => $asignaciones->map(fn (AsignacionHorario $asignacion) => [
                'id' => $asignacion->id,
                'horario' => ['id' => $asignacion->horario->id, 'nombre' => $asignacion->horario->nombre],
                'vigente_desde' => $asignacion->vigente_desde->toDateString(),
                'vigente_hasta' => $asignacion->vigente_hasta?->toDateString(),
                'es_vigente' => $asignacion->esVigente(),
                'delete_url' => route('asignaciones-horario.destroy', $asignacion),
            ])->values(),
        ]);
    }

    public function store(AsignacionHorarioRequest $request, string $miembro): RedirectResponse|JsonResponse
    {
        $miembroModel = MiembroEquipo::where('tenant_id', tenant()->getTenantKey())->findOrFail($miembro);
        $horario = Horario::where('tenant_id', tenant()->getTenantKey())->findOrFail($request->validated('horario_id'));

        try {
            $this->asignadorHorario->asignar($miembroModel, $horario, Carbon::parse($request->validated('vigente_desde')));
        } catch (AsignacionHorarioSolapadaException $e) {
            if ($request->wantsJson()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return back()->with('error', $e->getMessage());
        }

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Horario asignado correctamente.'], 201);
        }

        return redirect()->route('miembros-equipo.index')->with('success', 'Horario asignado correctamente.');
    }

    public function destroy(Request $request, string $asignacion): RedirectResponse|JsonResponse
    {
        $asignacionModel = AsignacionHorario::where('tenant_id', tenant()->getTenantKey())->findOrFail($asignacion);
        $asignacionModel->delete();

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Asignación eliminada correctamente.']);
        }

        return redirect()->route('miembros-equipo.index')->with('success', 'Asignación eliminada correctamente.');
    }
}
