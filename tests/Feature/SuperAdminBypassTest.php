<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\GestionaRolesDeTenant;
use Tests\TestCase;

class SuperAdminBypassTest extends TestCase
{
    use GestionaRolesDeTenant, RefreshDatabase;

    public function test_super_admin_pasa_cualquier_can_por_gate_before(): void
    {
        $this->sembrarPermisos();
        $superAdmin = User::factory()->superAdmin()->create(['password' => bcrypt('secret123')]);

        $this->assertTrue($superAdmin->can('ver-facturas'));
        $this->assertTrue($superAdmin->can('ver-roles'));
        $this->assertTrue($superAdmin->can('ver-logs'));
    }

    public function test_rutas_super_admin_siguen_exigiendo_ensure_super_admin(): void
    {
        $this->sembrarPermisos();
        $tenant = Tenant::factory()->create();
        // Usuario de tenant con TODO el catálogo: aun así no entra a super_admin.*
        $rol = $this->crearRol($tenant, 'Administrador', \App\Support\CatalogoPermisos::claves());
        $usuario = $this->usuarioConRol($tenant, $rol);

        $this->loginAs($usuario);

        $this->get('http://localhost/super_admin/tenants')->assertForbidden();
    }

    public function test_super_admin_accede_a_su_seccion(): void
    {
        $this->sembrarPermisos();
        $superAdmin = User::factory()->superAdmin()->create(['password' => bcrypt('secret123')]);

        $this->loginAs($superAdmin);

        $this->get('http://localhost/super_admin/tenants')->assertOk();
    }
}
