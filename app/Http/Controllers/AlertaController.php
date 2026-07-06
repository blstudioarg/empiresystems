<?php

namespace App\Http\Controllers;

use App\Enums\EstadoAlerta;
use App\Models\Alerta;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AlertaController extends Controller
{
    public function index(Request $request): View|JsonResponse
    {
        $query = Alerta::where('tenant_id', tenant()->getTenantKey())
            ->with(['miembro.user', 'fichaje'])
            ->when($request->filled('estado'), fn ($q) => $q->where('estado', $request->string('estado')->toString()))
            ->orderByDesc('created_at');

        if ($request->wantsJson()) {
            $todas = Alerta::where('tenant_id', tenant()->getTenantKey())->get(['estado']);

            return response()->json([
                'data' => $query->get()->map(fn (Alerta $alerta) => [
                    'id' => $alerta->id,
                    'miembro' => $alerta->miembro->user->name,
                    'tipo' => $alerta->tipo->value,
                    'tipo_label' => $alerta->tipo->label(),
                    'fichaje_fecha' => $alerta->fichaje?->ocurrido_at->enZonaTenant()->format('d/m/Y H:i'),
                    'referencia_fecha' => $alerta->referencia_fecha?->format('d/m/Y'),
                    'distancia_metros' => $alerta->distancia_metros,
                    'estado' => $alerta->estado->value,
                    'estado_label' => $alerta->estado->label(),
                    'update_url' => route('alertas.update', $alerta),
                ])->values(),
                'totales' => [
                    'total' => $todas->count(),
                    'nuevas' => $todas->where('estado', EstadoAlerta::Nueva)->count(),
                    'resueltas' => $todas->where('estado', EstadoAlerta::Resuelta)->count(),
                ],
            ]);
        }

        return view('alertas.index');
    }

    public function update(Request $request, string $alerta): RedirectResponse|JsonResponse
    {
        $datos = $request->validate([
            'estado' => ['required', Rule::in(array_map(fn (EstadoAlerta $estado) => $estado->value, EstadoAlerta::cases()))],
        ]);

        $alertaModel = Alerta::where('tenant_id', tenant()->getTenantKey())->findOrFail($alerta);

        $nuevoEstado = EstadoAlerta::from($datos['estado']);

        $alertaModel->update([
            'estado' => $nuevoEstado,
            'resuelta_por' => $nuevoEstado === EstadoAlerta::Resuelta ? Auth::id() : $alertaModel->resuelta_por,
            'resuelta_at' => $nuevoEstado === EstadoAlerta::Resuelta ? now() : $alertaModel->resuelta_at,
        ]);

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Alerta actualizada correctamente.']);
        }

        return redirect()->route('alertas.index')->with('success', 'Alerta actualizada correctamente.');
    }
}
