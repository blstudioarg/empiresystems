<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRolRequest;
use App\Http\Requests\UpdateRolRequest;
use App\Models\User;
use App\Support\CatalogoPermisos;
use App\Support\ProvisionadorRoles;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolController extends Controller
{
    public function index(Request $request): View|JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        // Defensa en profundidad (Principio I): además del team activo de spatie, se filtra
        // explícitamente por tenant_id (mismo patrón que UsuarioController/TenantController).
        $roles = Role::where('tenant_id', $tenantId)->orderBy('name')->get();

        if ($request->wantsJson()) {
            return response()->json([
                'data' => $roles->map(fn (Role $rol) => [
                    'id' => $rol->id,
                    'name' => $rol->name,
                    'es_administrador' => $rol->name === ProvisionadorRoles::ROL_ADMINISTRADOR,
                    'es_defecto' => (bool) $rol->es_defecto,
                    'permisos' => $rol->permissions->pluck('name')->values(),
                    'num_permisos' => $rol->permissions->count(),
                    'num_usuarios' => User::where('tenant_id', $tenantId)->whereHas(
                        'roles', fn ($query) => $query->where('roles.id', $rol->id)
                    )->count(),
                    'update_url' => route('roles.update', $rol),
                    'delete_url' => route('roles.destroy', $rol),
                    'defecto_url' => route('roles.defecto.update', $rol),
                ])->values(),
                'catalogo' => CatalogoPermisos::porModulo(),
                'totales' => [
                    'roles' => $roles->count(),
                    'usuarios_con_rol' => User::where('tenant_id', $tenantId)->whereHas('roles')->count(),
                    'permisos_catalogo' => count(CatalogoPermisos::claves()),
                ],
            ]);
        }

        return view('roles.index');
    }

    public function store(StoreRolRequest $request): RedirectResponse|JsonResponse
    {
        $datos = $request->validated();

        $rol = Role::create([
            'name' => $datos['name'],
            'guard_name' => 'web',
            'tenant_id' => $request->user()->tenant_id,
        ]);
        $rol->syncPermissions($datos['permisos']);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Rol creado correctamente.'], 201);
        }

        return redirect()->route('roles.index')->with('success', 'Rol creado correctamente.');
    }

    public function update(UpdateRolRequest $request, string $rol): RedirectResponse|JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $rolModelo = Role::where('tenant_id', $tenantId)->findOrFail($rol);
        $datos = $request->validated();

        $esAdministrador = $rolModelo->name === ProvisionadorRoles::ROL_ADMINISTRADOR;

        if ($esAdministrador) {
            if ($datos['name'] !== ProvisionadorRoles::ROL_ADMINISTRADOR) {
                return $this->error($request, 'El rol Administrador no se puede renombrar.', 422);
            }

            $pierdeAccesoGestion = ! in_array('ver-roles', $datos['permisos'], true)
                || ! in_array('ver-usuarios', $datos['permisos'], true);

            if ($pierdeAccesoGestion) {
                return $this->error($request, 'El rol Administrador no puede perder los permisos "Roles" ni "Usuarios".', 422);
            }
        }

        $rolModelo->update(['name' => $datos['name']]);
        $rolModelo->syncPermissions($datos['permisos']);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Rol actualizado correctamente.']);
        }

        return redirect()->route('roles.index')->with('success', 'Rol actualizado correctamente.');
    }

    public function destroy(Request $request, string $rol): RedirectResponse|JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $rolModelo = Role::where('tenant_id', $tenantId)->findOrFail($rol);

        if ($rolModelo->name === ProvisionadorRoles::ROL_ADMINISTRADOR) {
            return $this->error($request, 'El rol Administrador no se puede eliminar.', 409);
        }

        if ($rolModelo->es_defecto) {
            return $this->error($request, 'No se puede eliminar el rol por defecto. Marcá otro rol como defecto primero.', 409);
        }

        $tieneUsuarios = User::where('tenant_id', $tenantId)
            ->whereHas('roles', fn ($query) => $query->where('roles.id', $rolModelo->id))
            ->exists();

        if ($tieneUsuarios) {
            return $this->error($request, 'No se puede eliminar un rol con usuarios asignados.', 409);
        }

        $rolModelo->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Rol eliminado correctamente.']);
        }

        return redirect()->route('roles.index')->with('success', 'Rol eliminado correctamente.');
    }

    public function actualizarDefecto(Request $request, string $rol): RedirectResponse|JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $rolModelo = Role::where('tenant_id', $tenantId)->findOrFail($rol);

        DB::transaction(function () use ($rolModelo, $tenantId) {
            Role::where('tenant_id', $tenantId)->where('id', '!=', $rolModelo->id)
                ->where('es_defecto', true)
                ->update(['es_defecto' => false]);

            $rolModelo->update(['es_defecto' => true]);
        });

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Rol por defecto actualizado.']);
        }

        return redirect()->route('roles.index')->with('success', 'Rol por defecto actualizado.');
    }

    private function error(Request $request, string $mensaje, int $status): RedirectResponse|JsonResponse
    {
        if ($request->wantsJson()) {
            return response()->json(['message' => $mensaje], $status);
        }

        return redirect()->route('roles.index')->with('error', $mensaje);
    }
}
