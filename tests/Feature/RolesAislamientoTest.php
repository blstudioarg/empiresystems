<?php

namespace Tests\Feature;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\GestionaRolesDeTenant;
use Tests\TestCase;

/**
 * Principio I: los roles de un tenant no existen ni son asignables desde otro (≥2 tenants).
 */
class RolesAislamientoTest extends TestCase
{
    use GestionaRolesDeTenant, RefreshDatabase;

    public function test_rol_de_tenant_a_no_es_visible_con_el_team_de_tenant_b(): void
    {
        $this->sembrarPermisos();
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $this->crearRol($tenantA, 'Ventas', ['ver-clientes']);

        $registrar = app(PermissionRegistrar::class);

        $registrar->setPermissionsTeamId($tenantA->getTenantKey());
        $this->assertNotNull(Role::where('name', 'Ventas')->first());

        $registrar->setPermissionsTeamId($tenantB->getTenantKey());
        $this->assertNull(
            Role::where('name', 'Ventas')->where('tenant_id', $tenantB->getTenantKey())->first()
        );
    }

    public function test_usuario_de_b_no_puede_recibir_rol_de_a(): void
    {
        $this->sembrarPermisos();
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $rolA = $this->crearRol($tenantA, 'Ventas', ['ver-clientes', 'ver-facturas']);
        $usuarioB = $this->usuarioConRol($tenantB, null);

        // Con el team de B, el rol de A no está en scope: asignarlo lanza excepción de spatie.
        $this->enTeam($tenantB, function () use ($usuarioB, $rolA) {
            $this->expectException(\Spatie\Permission\Exceptions\RoleDoesNotExist::class);
            $usuarioB->assignRole('Ventas');

            // Además el permiso de A no cruza aunque se referencie por id.
            $this->assertFalse($usuarioB->hasPermissionTo('ver-facturas'));
        });
    }

    public function test_permisos_efectivos_no_cruzan_tenants(): void
    {
        $this->sembrarPermisos();
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $usuarioA = $this->usuarioConRol($tenantA, $this->crearRol($tenantA, 'Ventas', ['ver-facturas']));
        $usuarioB = $this->usuarioConRol($tenantB, $this->crearRol($tenantB, 'Soporte', ['ver-clientes']));

        $this->enTeam($tenantA, fn () => $this->assertTrue($usuarioA->fresh()->can('ver-facturas')));
        $this->enTeam($tenantB, function () use ($usuarioB) {
            $this->assertTrue($usuarioB->fresh()->can('ver-clientes'));
            $this->assertFalse($usuarioB->fresh()->can('ver-facturas'));
        });
    }
}
