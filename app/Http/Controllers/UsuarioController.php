<?php

namespace App\Http\Controllers;

use App\Enums\AccionLogActividad;
use App\Enums\EntidadLogActividad;
use App\Enums\EstadoUsuario;
use App\Models\User;
use App\Services\RegistradorActividad;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class UsuarioController extends Controller
{
    public function __construct(
        private readonly RegistradorActividad $registradorActividad,
    ) {}

    public function index(Request $request): View|JsonResponse
    {
        $tenantId = auth()->user()->tenant_id;
        $usuarios = User::where('tenant_id', $tenantId)
            ->with('roles')
            ->orderBy('name')
            ->get();

        $totales = [
            'total' => $usuarios->count(),
            'pendientes' => $usuarios->where('estado', EstadoUsuario::Pendiente)->count(),
            'activos' => $usuarios->where('activo', true)->count(),
        ];

        if ($request->wantsJson()) {
            $rolesDisponibles = Role::where('tenant_id', $tenantId)->orderBy('name')->get(['id', 'name']);

            return response()->json([
                'data' => $usuarios->map(function (User $usuario) {
                    $rol = $usuario->roles->first();

                    return [
                        'id' => $usuario->id,
                        'name' => $usuario->name,
                        'email' => $usuario->email,
                        'rol' => $usuario->rol->value,
                        'estado' => $usuario->estado->value,
                        'activo' => $usuario->activo,
                        'es_actual' => $usuario->id === auth()->id(),
                        'rol_asignado' => $rol ? ['id' => $rol->id, 'name' => $rol->name] : null,
                        'rol_url' => route('usuarios.rol.update', $usuario),
                        'aprobar_url' => route('usuarios.aprobar', $usuario),
                        'rechazar_url' => route('usuarios.rechazar', $usuario),
                    ];
                })->values(),
                'totales' => $totales,
                'roles_disponibles' => $rolesDisponibles,
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

        $this->registradorActividad->registrar(
            auth()->user(),
            AccionLogActividad::Alta,
            EntidadLogActividad::Usuario,
            $usuario->id,
            "Aprobó al usuario {$usuario->name}",
        );

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

        $this->registradorActividad->registrar(
            auth()->user(),
            AccionLogActividad::Baja,
            EntidadLogActividad::Usuario,
            $usuario->id,
            "Rechazó al usuario {$usuario->name}",
        );

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Usuario rechazado correctamente.']);
        }

        return redirect()->route('usuarios.index')->with('success', 'Usuario rechazado correctamente.');
    }

    /**
     * Asigna (o quita, con `role_id: null`) el rol de un usuario del tenant activo (feature 027,
     * FR-006). Anti-lockout server-side: ninguna reasignación puede dejar al tenant sin ningún
     * usuario activo con `ver-roles` y `ver-usuarios` a la vez (RN-02).
     */
    public function actualizarRol(Request $request, string $usuario): RedirectResponse|JsonResponse
    {
        $tenantId = auth()->user()->tenant_id;
        $usuarioModelo = User::where('tenant_id', $tenantId)->findOrFail($usuario);

        $datos = $request->validate([
            'role_id' => ['nullable', 'integer'],
        ]);

        $rol = null;
        if (! empty($datos['role_id'])) {
            $rol = Role::where('tenant_id', $tenantId)->findOrFail($datos['role_id']);
        }

        $roles = Role::where('tenant_id', $tenantId)->get()->keyBy('id');
        $rolesConAmbos = $roles->filter(
            fn (Role $r) => $r->hasPermissionTo('ver-roles') && $r->hasPermissionTo('ver-usuarios')
        )->keys()->all();

        $usuariosActivos = User::where('tenant_id', $tenantId)->where('activo', true)->with('roles')->get();

        $quedaAlgunoConAcceso = $usuariosActivos->contains(function (User $u) use ($usuarioModelo, $rol, $rolesConAmbos) {
            $rolIdEfectivo = $u->id === $usuarioModelo->id
                ? $rol?->id
                : $u->roles->first()?->id;

            return $rolIdEfectivo && in_array($rolIdEfectivo, $rolesConAmbos, true);
        });

        if (! $quedaAlgunoConAcceso) {
            $mensaje = 'El tenant quedaría sin ningún administrador (nadie con acceso a Roles y Usuarios).';

            if ($request->wantsJson()) {
                return response()->json(['message' => $mensaje], 422);
            }

            return redirect()->route('usuarios.index')->with('error', $mensaje);
        }

        $usuarioModelo->syncRoles($rol ? [$rol] : []);

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Rol asignado correctamente.']);
        }

        return redirect()->route('usuarios.index')->with('success', 'Rol asignado correctamente.');
    }
}
