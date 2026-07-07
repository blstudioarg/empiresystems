<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Support\CatalogoPermisos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\GestionaRolesDeTenant;
use Tests\TestCase;

class AsignacionRolUsuarioTest extends TestCase
{
    use GestionaRolesDeTenant, RefreshDatabase;

    public function test_ruta_exige_ver_usuarios(): void
    {
        $this->sembrarPermisos();
        $tenant = Tenant::factory()->create();
        $usuario = $this->usuarioConRol($tenant, $this->crearRol($tenant, 'Ventas', ['ver-clientes']));

        $this->loginAs($usuario);

        $this->patchJson("/usuarios/{$usuario->id}/rol", ['role_id' => null])->assertForbidden();
    }

    public function test_asigna_rol_del_mismo_tenant(): void
    {
        $this->sembrarPermisos();
        $tenant = Tenant::factory()->create();
        $rolAdmin = $this->crearRol($tenant, 'Administrador', CatalogoPermisos::claves());
        $rolVentas = $this->crearRol($tenant, 'Ventas', ['ver-clientes']);
        $admin = $this->usuarioConRol($tenant, $rolAdmin);
        $empleado = $this->usuarioConRol($tenant, null);

        $this->loginAs($admin);

        $this->patchJson("/usuarios/{$empleado->id}/rol", ['role_id' => $rolVentas->id])->assertOk();

        $this->enTeam($tenant, function () use ($empleado, $rolVentas) {
            $this->assertTrue($empleado->fresh()->hasRole($rolVentas));
        });
    }

    public function test_rol_de_otro_tenant_da_404(): void
    {
        $this->sembrarPermisos();
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $rolAdminA = $this->crearRol($tenantA, 'Administrador', CatalogoPermisos::claves());
        $admin = $this->usuarioConRol($tenantA, $rolAdminA);
        $empleado = $this->usuarioConRol($tenantA, null);
        $rolB = $this->crearRol($tenantB, 'Ventas', ['ver-clientes']);

        $this->loginAs($admin);

        $this->patchJson("/usuarios/{$empleado->id}/rol", ['role_id' => $rolB->id])->assertNotFound();
    }

    public function test_role_id_null_deja_sin_rol(): void
    {
        $this->sembrarPermisos();
        $tenant = Tenant::factory()->create();
        $rolAdmin = $this->crearRol($tenant, 'Administrador', CatalogoPermisos::claves());
        $rolVentas = $this->crearRol($tenant, 'Ventas', ['ver-clientes']);
        $admin = $this->usuarioConRol($tenant, $rolAdmin);
        $empleado = $this->usuarioConRol($tenant, $rolVentas);

        $this->loginAs($admin);

        $this->patchJson("/usuarios/{$empleado->id}/rol", ['role_id' => null])->assertOk();

        $this->enTeam($tenant, function () use ($empleado) {
            $this->assertCount(0, $empleado->fresh()->roles);
        });
    }

    public function test_quitar_el_ultimo_acceso_a_roles_y_usuarios_da_422(): void
    {
        $this->sembrarPermisos();
        $tenant = Tenant::factory()->create();
        $rolAdmin = $this->crearRol($tenant, 'Administrador', CatalogoPermisos::claves());
        $unicoAdmin = $this->usuarioConRol($tenant, $rolAdmin);

        $this->loginAs($unicoAdmin);

        $this->patchJson("/usuarios/{$unicoAdmin->id}/rol", ['role_id' => null])->assertStatus(422);

        $this->enTeam($tenant, function () use ($unicoAdmin, $rolAdmin) {
            $this->assertTrue($unicoAdmin->fresh()->hasRole($rolAdmin));
        });
    }

    public function test_quitar_acceso_es_valido_si_otro_usuario_activo_mantiene_ambos_permisos(): void
    {
        $this->sembrarPermisos();
        $tenant = Tenant::factory()->create();
        $rolAdmin = $this->crearRol($tenant, 'Administrador', CatalogoPermisos::claves());
        $admin1 = $this->usuarioConRol($tenant, $rolAdmin);
        $admin2 = $this->usuarioConRol($tenant, $rolAdmin);

        $this->loginAs($admin1);

        $this->patchJson("/usuarios/{$admin2->id}/rol", ['role_id' => null])->assertOk();
    }

    public function test_usuario_sin_rol_solo_accede_a_secciones_personales(): void
    {
        $this->sembrarPermisos();
        $tenant = Tenant::factory()->create();
        $rolAdmin = $this->crearRol($tenant, 'Administrador', CatalogoPermisos::claves());
        $admin = $this->usuarioConRol($tenant, $rolAdmin);
        $empleado = $this->usuarioConRol($tenant, $this->crearRol($tenant, 'Ventas', ['ver-clientes']));

        $this->loginAs($admin);
        $this->patchJson("/usuarios/{$empleado->id}/rol", ['role_id' => null])->assertOk();
        $this->post('/logout');

        $this->loginAs($empleado);
        $this->get('/clientes')->assertForbidden();
        $this->get('/fichajes')->assertOk();
    }
}
