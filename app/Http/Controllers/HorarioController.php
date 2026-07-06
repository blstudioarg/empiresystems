<?php

namespace App\Http\Controllers;

use App\Http\Requests\HorarioRequest;
use App\Models\Horario;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class HorarioController extends Controller
{
    public function index(Request $request): View|JsonResponse
    {
        $horarios = Horario::where('tenant_id', tenant()->getTenantKey())
            ->withCount('asignaciones')
            ->with('tramos')
            ->orderBy('nombre')
            ->get();

        if ($request->wantsJson()) {
            return response()->json([
                'data' => $horarios->map(fn (Horario $horario) => [
                    'id' => $horario->id,
                    'nombre' => $horario->nombre,
                    'activo' => $horario->activo,
                    'horas_semana' => $horario->horasPrevistasSemana(),
                    'num_asignaciones' => $horario->asignaciones_count,
                    'tramos' => $horario->tramos->map(fn ($tramo) => [
                        'dia_semana' => $tramo->dia_semana,
                        'hora_inicio' => substr($tramo->hora_inicio, 0, 5),
                        'hora_fin' => substr($tramo->hora_fin, 0, 5),
                    ])->values(),
                    'update_url' => route('horarios.update', $horario),
                    'delete_url' => route('horarios.destroy', $horario),
                ])->values(),
            ]);
        }

        return view('horarios.index');
    }

    public function store(HorarioRequest $request): RedirectResponse|JsonResponse
    {
        $horario = DB::transaction(function () use ($request) {
            $horario = Horario::create([
                'tenant_id' => tenant()->getTenantKey(),
                'nombre' => $request->validated('nombre'),
                'activo' => $request->boolean('activo', true),
            ]);

            $this->sincronizarTramos($horario, $request->validated('tramos', []));

            return $horario;
        });

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Horario creado correctamente.', 'id' => $horario->id], 201);
        }

        return redirect()->route('horarios.index')->with('success', 'Horario creado correctamente.');
    }

    public function update(HorarioRequest $request, string $horario): RedirectResponse|JsonResponse
    {
        $horarioModel = Horario::where('tenant_id', tenant()->getTenantKey())->findOrFail($horario);

        DB::transaction(function () use ($request, $horarioModel) {
            $horarioModel->update([
                'nombre' => $request->validated('nombre'),
                'activo' => $request->boolean('activo', true),
            ]);

            $horarioModel->tramos()->delete();
            $this->sincronizarTramos($horarioModel, $request->validated('tramos', []));
        });

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Horario actualizado correctamente.']);
        }

        return redirect()->route('horarios.index')->with('success', 'Horario actualizado correctamente.');
    }

    public function destroy(Request $request, string $horario): RedirectResponse|JsonResponse
    {
        $horarioModel = Horario::where('tenant_id', tenant()->getTenantKey())->findOrFail($horario);

        if ($horarioModel->asignaciones()->exists()) {
            $mensaje = 'No se puede eliminar un horario con asignaciones (vigentes o históricas).';

            if ($request->wantsJson()) {
                return response()->json(['message' => $mensaje], 422);
            }

            return redirect()->route('horarios.index')->with('error', $mensaje);
        }

        $horarioModel->delete();

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Horario eliminado correctamente.']);
        }

        return redirect()->route('horarios.index')->with('success', 'Horario eliminado correctamente.');
    }

    /**
     * @param  array<int, array{dia_semana: int, hora_inicio: string, hora_fin: string}>  $tramos
     */
    private function sincronizarTramos(Horario $horario, array $tramos): void
    {
        foreach ($tramos as $tramo) {
            $horario->tramos()->create([
                'tenant_id' => $horario->tenant_id,
                'dia_semana' => $tramo['dia_semana'],
                'hora_inicio' => $tramo['hora_inicio'],
                'hora_fin' => $tramo['hora_fin'],
            ]);
        }
    }
}
