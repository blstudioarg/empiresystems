<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\User;
use App\Support\CatalogoPermisos;
use App\Support\ProvisionadorRoles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Simula la migración de datos de tenants pre-spatie (FR-008): 2 tenants con usuarios
 * `rol=admin`/`rol=usuario` y sin ningún rol spatie asignado, tal como estaban antes de esta
 * feature. Tras correr la migración manualmente, cada tenant debe quedar con sus roles
 * Administrador/Usuario y cada usuario con el rol correspondiente (SC-005).
 */
class MigracionRolesTenantsExistentesTest extends TestCase
{
    use RefreshDatabase;

    private function ejecutarMigracion(): void
    {
        $migracion = require base_path('database/migrations/2026_07_06_235000_provisionar_roles_tenants_existentes.php');
        $migracion->up();
    }

    public function test_tenants_preexistentes_quedan_con_roles_equivalentes_al_enum(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $adminA = User::factory()->admin()->create(['tenant_id' => $tenantA->id]);
        $usuarioA = User::factory()->create(['tenant_id' => $tenantA->id]);
        $adminB = User::factory()->admin()->create(['tenant_id' => $tenantB->id]);
        $usuarioB = User::factory()->create(['tenant_id' => $tenantB->id]);

        // Simula el estado "pre-spatie": ningún usuario tiene rol asignado todavía.
        foreach ([$adminA, $usuarioA, $adminB, $usuarioB] as $user) {
            app(PermissionRegistrar::class)->setPermissionsTeamId($user->tenant_id);
            $user->syncRoles([]);
        }
        Role::query()->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->ejecutarMigracion();

        $registrar = app(PermissionRegistrar::class);

        foreach ([$tenantA, $tenantB] as $tenant) {
            $registrar->setPermissionsTeamId($tenant->getTenantKey());

            $rolAdmin = Role::where('tenant_id', $tenant->getTenantKey())
                ->where('name', ProvisionadorRoles::ROL_ADMINISTRADOR)->first();
            $rolUsuario = Role::where('tenant_id', $tenant->getTenantKey())
                ->where('name', ProvisionadorRoles::ROL_USUARIO)->first();

            $this->assertNotNull($rolAdmin);
            $this->assertNotNull($rolUsuario);
            $this->assertCount(20, $rolAdmin->permissions);
            $this->assertTrue((bool) $rolUsuario->es_defecto);
        }

        $registrar->setPermissionsTeamId($tenantA->getTenantKey());
        $this->assertTrue($adminA->fresh()->hasRole(ProvisionadorRoles::ROL_ADMINISTRADOR));
        $this->assertTrue($usuarioA->fresh()->hasRole(ProvisionadorRoles::ROL_USUARIO));

        $registrar->setPermissionsTeamId($tenantB->getTenantKey());
        $this->assertTrue($adminB->fresh()->hasRole(ProvisionadorRoles::ROL_ADMINISTRADOR));
        $this->assertTrue($usuarioB->fresh()->hasRole(ProvisionadorRoles::ROL_USUARIO));
    }

    public function test_equivalencia_de_accesos_tras_la_migracion(): void
    {
        $tenant = Tenant::factory()->create();
        $admin = User::factory()->admin()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $usuario = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);

        foreach ([$admin, $usuario] as $user) {
            app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->getTenantKey());
            $user->syncRoles([]);
        }
        Role::query()->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->ejecutarMigracion();

        $this->loginAs($admin);
        $this->get('/facturas')->assertOk();
        $this->get('/usuarios')->assertOk();
        $this->post('/logout');

        $this->loginAs($usuario);
        $this->get('/facturas')->assertOk();
        $this->get('/usuarios')->assertForbidden();
    }
}
