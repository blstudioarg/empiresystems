<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Tenant;
use App\Support\CatalogoPermisos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\GestionaRolesDeTenant;
use Tests\TestCase;

class SidebarPermisosTest extends TestCase
{
    use GestionaRolesDeTenant, RefreshDatabase;

    public function test_el_sidebar_muestra_solo_las_secciones_permitidas(): void
    {
        $this->sembrarPermisos();
        $tenant = Tenant::factory()->create();
        $usuario = $this->usuarioConRol($tenant, $this->crearRol($tenant, 'Ventas', ['ver-clientes']));

        $this->loginAs($usuario);
        $html = $this->get('/perfil')->assertOk()->getContent();

        // Ve Clientes, no ve Facturas ni el grupo Stock.
        $this->assertStringContainsString('Cartera de clientes', $html);
        $this->assertStringNotContainsString('Crear factura', $html);
        $this->assertStringNotContainsString('Kardex', $html);

        // Fichar/Mi jornada ya no son universales (doc 09, Cambio 5): con solo ver-clientes
        // (sin ver-fichar/ver-mi-jornada), el bloque Control de fichaje no aparece.
        $this->assertStringNotContainsString('>Fichar<', $html);
        $this->assertStringNotContainsString('>Mi jornada<', $html);
    }

    public function test_fichar_y_mi_jornada_se_muestran_con_su_permiso(): void
    {
        $this->sembrarPermisos();
        $tenant = Tenant::factory()->create();
        $usuario = $this->usuarioConRol($tenant, $this->crearRol($tenant, 'Operario', ['ver-fichar', 'ver-mi-jornada']));

        $this->loginAs($usuario);
        $html = $this->get('/perfil')->assertOk()->getContent();

        $this->assertStringContainsString('>Fichar<', $html);
        $this->assertStringContainsString('>Mi jornada<', $html);
    }

    public function test_grupo_sin_entradas_visibles_no_se_renderiza(): void
    {
        $this->sembrarPermisos();
        $tenant = Tenant::factory()->create();
        // Solo ver-clientes: el grupo Marketing (campañas/plantillas) no debe aparecer.
        $usuario = $this->usuarioConRol($tenant, $this->crearRol($tenant, 'Ventas', ['ver-clientes']));

        $this->loginAs($usuario);
        $html = $this->get('/perfil')->assertOk()->getContent();

        $this->assertStringNotContainsString('>Marketing<', $html);
        $this->assertStringNotContainsString('Nueva campaña', $html);
    }

    public function test_admin_con_todo_el_catalogo_ve_facturas_y_stock(): void
    {
        $this->sembrarPermisos();
        $tenant = Tenant::factory()->create();
        $rol = $this->crearRol($tenant, 'Administrador', CatalogoPermisos::claves());
        $usuario = $this->usuarioConRol($tenant, $rol, ['rol' => UserRole::Admin]);

        $this->loginAs($usuario);
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('Crear factura', $html);
        $this->assertStringContainsString('Kardex', $html);
        $this->assertStringContainsString('Administrador', $html);
    }
}
