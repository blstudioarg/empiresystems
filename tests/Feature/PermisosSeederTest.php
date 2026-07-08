<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Support\CatalogoPermisos;
use App\Support\ProvisionadorRoles;
use Database\Seeders\PermisosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class PermisosSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_siembra_las_20_claves_del_catalogo(): void
    {
        $this->seed(PermisosSeeder::class);

        $this->assertSame(20, Permission::count());
        foreach (CatalogoPermisos::claves() as $clave) {
            $this->assertDatabaseHas('permissions', ['name' => $clave, 'guard_name' => 'web']);
        }
    }

    public function test_reejecutar_no_duplica_permisos(): void
    {
        $this->seed(PermisosSeeder::class);
        $this->seed(PermisosSeeder::class);

        $this->assertSame(20, Permission::count());
    }

    public function test_permiso_nuevo_llega_al_rol_administrador_pero_no_a_otros_roles(): void
    {
        $this->seed(PermisosSeeder::class);

        $tenant = Tenant::factory()->create();
        app(ProvisionadorRoles::class)->provisionarAdministrador($tenant);

        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId($tenant->getTenantKey());

        $ventas = Role::create(['name' => 'Ventas', 'guard_name' => 'web', 'tenant_id' => $tenant->getTenantKey()]);
        $ventas->syncPermissions(['ver-clientes']);

        // Simula que `ver-facturas` es un permiso "nuevo": el rol Administrador aún no lo tenía
        // (se lo quitamos a mano) y Ventas nunca lo tuvo. Re-sembrar debe devolvérselo solo al
        // Administrador.
        $admin = Role::where('tenant_id', $tenant->getTenantKey())
            ->where('name', ProvisionadorRoles::ROL_ADMINISTRADOR)->first();
        $admin->revokePermissionTo('ver-facturas');
        $registrar->forgetCachedPermissions();

        $this->assertFalse($admin->fresh()->hasPermissionTo('ver-facturas'));

        $this->seed(PermisosSeeder::class);

        $registrar->setPermissionsTeamId($tenant->getTenantKey());
        $registrar->forgetCachedPermissions();

        $admin = Role::where('tenant_id', $tenant->getTenantKey())
            ->where('name', ProvisionadorRoles::ROL_ADMINISTRADOR)->first();
        $ventas = Role::where('tenant_id', $tenant->getTenantKey())->where('name', 'Ventas')->first();

        $this->assertTrue($admin->hasPermissionTo('ver-facturas'));
        $this->assertFalse($ventas->hasPermissionTo('ver-facturas'));
    }
}
