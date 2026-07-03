<?php

namespace App\Http\Controllers;

use App\Enums\EstadoUsuario;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UsuarioController extends Controller
{
    public function index(Request $request): View|JsonResponse
    {
        $usuarios = User::where('tenant_id', auth()->user()->tenant_id)
            ->orderBy('name')
            ->get();

        $totales = [
            'total' => $usuarios->count(),
            'pendientes' => $usuarios->where('estado', EstadoUsuario::Pendiente)->count(),
            'activos' => $usuarios->where('activo', true)->count(),
        ];

        if ($request->wantsJson()) {
            return response()->json([
                'data' => $usuarios->map(fn (User $usuario) => [
                    'id' => $usuario->id,
                    'name' => $usuario->name,
                    'email' => $usuario->email,
                    'rol' => $usuario->rol->value,
                    'estado' => $usuario->estado->value,
                    'activo' => $usuario->activo,
                    'es_actual' => $usuario->id === auth()->id(),
                    'aprobar_url' => route('usuarios.aprobar', $usuario),
                    'rechazar_url' => route('usuarios.rechazar', $usuario),
                ])->values(),
                'totales' => $totales,
            ]);
        }

        return view('usuarios.index', [
            'usuarios' => $usuarios,
            'totales' => $totales,
        ]);
    }

    public function aprobar(Request $request, string $usuario): RedirectResponse|JsonResponse
    {
        $usuario = User::where('tenant_id', auth()->user()->tenant_id)->findOrFail($usuario);

        abort_if($usuario->id === auth()->id(), 403, 'No podés aprobar tu propia cuenta.');

        $usuario->aprobar(auth()->user());

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Usuario aprobado correctamente.']);
        }

        return redirect()->route('usuarios.index')->with('success', 'Usuario aprobado correctamente.');
    }

    public function rechazar(Request $request, string $usuario): RedirectResponse|JsonResponse
    {
        $usuario = User::where('tenant_id', auth()->user()->tenant_id)->findOrFail($usuario);

        abort_if($usuario->id === auth()->id(), 403, 'No podés rechazar tu propia cuenta.');

        $usuario->rechazar();

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Usuario rechazado correctamente.']);
        }

        return redirect()->route('usuarios.index')->with('success', 'Usuario rechazado correctamente.');
    }
}
