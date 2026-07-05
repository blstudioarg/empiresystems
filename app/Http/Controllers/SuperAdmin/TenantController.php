<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Enums\EstadoFactura;
use App\Enums\EstadoUsuario;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\StoreTenantRequest;
use App\Http\Requests\SuperAdmin\UpdateTenantRequest;
use App\Models\Factura;
use App\Models\Provincia;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;
use Stancl\Tenancy\Database\Models\Domain;

class TenantController extends Controller
{
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

            User::create([
                'name' => 'Administrador',
                'email' => $datos['admin_email'],
                'password' => Hash::make($datos['admin_password']),
                'tenant_id' => $tenant->id,
                'rol' => UserRole::Admin,
                'estado' => EstadoUsuario::Aprobado,
                'activo' => true,
            ]);
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
