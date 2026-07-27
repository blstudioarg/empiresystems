<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Enums\AccionLogActividad;
use App\Enums\EntidadLogActividad;
use App\Enums\EstadoFactura;
use App\Enums\EstadoUsuario;
use App\Enums\ResultadoLogActividad;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\ActualizarUsuarioTenantRequest;
use App\Http\Requests\SuperAdmin\StoreTenantRequest;
use App\Http\Requests\SuperAdmin\UpdateTenantRequest;
use App\Models\Factura;
use App\Models\LogActividad;
use App\Models\Provincia;
use App\Models\Tenant;
use App\Models\User;
use App\Support\ProvisionadorRoles;
use App\Support\SembradorCanalesCaptacion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;
use Stancl\Tenancy\Database\Models\Domain;

class TenantController extends Controller
{
    public function __construct(
        private readonly ProvisionadorRoles $provisionadorRoles,
    ) {}

    /**
     * Contexto central (sin scope de tenant): lista todos los tenants con su dominio.
     */
    public function index(Request $request): View|JsonResponse
    {
        $tenants = Tenant::with('domains')->orderBy('nombre_comercial')->get();

        if ($request->wantsJson()) {
            return response()->json([
                'data' => $tenants->map(fn (Tenant $tenant) => [
                    'id' => $tenant->id,
                    'dominio' => $tenant->dominio()?->domain,
                    'nombre_comercial' => $tenant->nombre_comercial,
                    'razon_social' => $tenant->razon_social,
                    'nif' => $tenant->nif,
                    'direccion' => $tenant->direccion,
                    'cp' => $tenant->cp,
                    'ciudad' => $tenant->ciudad,
                    'provincia' => $tenant->provincia,
                    'pais' => $tenant->pais,
                    'regimen_impositivo' => $tenant->regimen_impositivo->value,
                    'email' => $tenant->email,
                    'activo' => $tenant->activo,
                    'update_url' => route('super_admin.tenants.update', $tenant),
                    'delete_url' => route('super_admin.tenants.destroy', $tenant),
                    'usuarios_url' => route('super_admin.tenants.usuarios', $tenant),
                ])->values(),
                'totales' => [
                    'total' => $tenants->count(),
                    'activos' => $tenants->where('activo', true)->count(),
                ],
            ]);
        }

        return view('super_admin.tenants.index', [
            'provincias' => Provincia::orderBy('nombre')->get(['id', 'nombre']),
        ]);
    }

    public function store(StoreTenantRequest $request): RedirectResponse|JsonResponse
    {
        $datos = $request->validated();

        DB::transaction(function () use ($datos) {
            $tenant = Tenant::create([
                'nombre_comercial' => $datos['nombre_comercial'],
                'razon_social' => $datos['razon_social'],
                'nif' => $datos['nif'],
                'direccion' => $datos['direccion'] ?? null,
                'cp' => $datos['cp'] ?? null,
                'ciudad' => $datos['ciudad'] ?? null,
                'provincia' => $datos['provincia'] ?? null,
                'pais' => $datos['pais'],
                'regimen_impositivo' => $datos['regimen_impositivo'],
                'email' => $datos['email'],
                'activo' => $datos['activo'],
            ]);

            Domain::create([
                'domain' => $datos['dominio'],
                'tenant_id' => $tenant->id,
            ]);

            $admin = User::create([
                'name' => 'Administrador',
                'email' => $datos['admin_email'],
                'password' => Hash::make($datos['admin_password']),
                'tenant_id' => $tenant->id,
                'rol' => UserRole::Admin,
                'estado' => EstadoUsuario::Aprobado,
                'activo' => true,
            ]);

            // Rol "Administrador" (catálogo completo) para el admin inicial + "Usuario" base por
            // defecto para altas públicas (feature 027, FR-007/FR-014). Misma transacción: si
            // algo falla aquí no queda tenant/usuario/rol parcial (RN-03).
            $this->provisionadorRoles->provisionarAdministrador($tenant, $admin);
            $this->provisionadorRoles->provisionarUsuarioBase($tenant);

            SembradorCanalesCaptacion::sembrar($tenant->id);
        });

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Tenant creado correctamente.'], 201);
        }

        return redirect()->route('super_admin.tenants.index')->with('success', 'Tenant creado correctamente.');
    }

    public function update(UpdateTenantRequest $request, Tenant $tenant): RedirectResponse|JsonResponse
    {
        $datos = $request->validated();

        DB::transaction(function () use ($datos, $tenant) {
            $tenant->update([
                'nombre_comercial' => $datos['nombre_comercial'],
                'razon_social' => $datos['razon_social'],
                'nif' => $datos['nif'],
                'direccion' => $datos['direccion'] ?? null,
                'cp' => $datos['cp'] ?? null,
                'ciudad' => $datos['ciudad'] ?? null,
                'provincia' => $datos['provincia'] ?? null,
                'pais' => $datos['pais'],
                'regimen_impositivo' => $datos['regimen_impositivo'],
                'email' => $datos['email'],
                'activo' => $datos['activo'],
            ]);

            $dominio = $tenant->dominio();

            if ($dominio) {
                $dominio->update(['domain' => $datos['dominio']]);
            } else {
                Domain::create(['domain' => $datos['dominio'], 'tenant_id' => $tenant->id]);
            }
        });

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Tenant actualizado correctamente.']);
        }

        return redirect()->route('super_admin.tenants.index')->with('success', 'Tenant actualizado correctamente.');
    }

    /**
     * Listado de usuarios de un tenant, para la sección "Usuarios" del modal de edición.
     */
    public function usuarios(Tenant $tenant): JsonResponse
    {
        $usuarios = User::where('tenant_id', $tenant->id)
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'rol', 'estado', 'activo'])
            ->map(fn (User $usuario) => [
                'id' => $usuario->id,
                'name' => $usuario->name,
                'email' => $usuario->email,
                'rol' => $usuario->rol,
                'estado' => $usuario->estado,
                'activo' => $usuario->activo,
                'update_url' => route('super_admin.tenants.usuarios.update', ['tenant' => $tenant->id, 'usuario' => $usuario->id]),
            ]);

        return response()->json(['data' => $usuarios->values()]);
    }

    /**
     * Edita el email de acceso y, opcionalmente, resetea la contraseña de un usuario del tenant.
     * No expone ni permite ver la contraseña actual (irreversible por diseño). Cada cambio queda
     * en `logs_actividad` (RGPD/LOPDGDD, constitución Principio II): el actor es el Super Admin,
     * pero el registro se asocia al tenant del usuario afectado, no al del actor (que no tiene).
     */
    public function actualizarUsuario(ActualizarUsuarioTenantRequest $request, Tenant $tenant, int $usuario): JsonResponse
    {
        $usuarioModelo = User::where('tenant_id', $tenant->id)->findOrFail($usuario);
        $datos = $request->validated();

        $emailAnterior = $usuarioModelo->email;
        $emailCambio = $datos['email'] !== $emailAnterior;
        $passwordCambio = ! empty($datos['password']);

        if (! $emailCambio && ! $passwordCambio) {
            return response()->json(['message' => 'No hay cambios que guardar.']);
        }

        $usuarioModelo->email = $datos['email'];

        if ($passwordCambio) {
            $usuarioModelo->password = Hash::make($datos['password']);
        }

        $usuarioModelo->save();

        $cambios = array_filter([
            $emailCambio ? "email ({$emailAnterior} → {$datos['email']})" : null,
            $passwordCambio ? 'contraseña' : null,
        ]);

        LogActividad::create([
            'tenant_id' => $tenant->id,
            'usuario_id' => $request->user()->id,
            'usuario_nombre' => $request->user()->name,
            'accion' => AccionLogActividad::Modificacion,
            'resultado' => ResultadoLogActividad::Exito,
            'ip_origen' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 255),
            'entidad_tipo' => EntidadLogActividad::Usuario,
            'entidad_id' => $usuarioModelo->id,
            'descripcion' => 'Super Admin modificó '.implode(' y ', $cambios)." del usuario {$usuarioModelo->name}.",
            'ocurrido_at' => now(),
        ]);

        return response()->json(['message' => 'Usuario actualizado correctamente.']);
    }

    public function destroy(Request $request, Tenant $tenant): RedirectResponse|JsonResponse
    {
        // Contexto central: el global scope de BelongsToTenant no está activo, así que se filtra
        // tenant_id explícitamente (data-model.md, research.md D5).
        $tieneFacturasEmitidas = Factura::where('tenant_id', $tenant->id)
            ->where('estado', '!=', EstadoFactura::Borrador)
            ->exists();

        if ($tieneFacturasEmitidas) {
            $mensaje = 'No se puede eliminar el tenant: tiene facturas emitidas. Podés desactivarlo en su lugar.';

            if ($request->wantsJson()) {
                return response()->json(['message' => $mensaje], 409);
            }

            return redirect()->route('super_admin.tenants.index')->with('error', $mensaje);
        }

        $tenant->delete();

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Tenant eliminado correctamente.']);
        }

        return redirect()->route('super_admin.tenants.index')->with('success', 'Tenant eliminado correctamente.');
    }
}
