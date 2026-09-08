<?php

namespace Tests\Feature\Pos;

use App\Models\Tenant;
use App\Support\ConfigPos;
use App\Support\MenuTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\GestionaRolesDeTenant;
use Tests\TestCase;

/**
 * FR-003: permiso del usuario y módulo activo en el tenant son **dos capas independientes**. Un
 * usuario con `ver-pos-sala` no entra si su tenant tiene el módulo apagado (research.md D6).
 *
 * Este test es el que impide que alguien "simplifique" el diseño resolviendo el acceso solo con
 * permisos y manipulándolos al encender/apagar el módulo.
 */
class ModuloApagadoTest extends TestCase
{
    use GestionaRolesDeTenant, RefreshDatabase;

    /** @return list<string> */
    private function rutasDelModulo(): array
    {
        return ['/pos/sala', '/pos/opciones'];
    }

    public function test_con_el_modulo_apagado_las_rutas_del_modulo_no_son_accesibles_aunque_haya_permiso(): void
    {
        $this->sembrarPermisos();
        $tenant = Tenant::factory()->create();
        $rol = $this->crearRol($tenant, 'Sala', ['ver-pos-sala', 'ver-pos-opciones']);
        $usuario = $this->usuarioConRol($tenant, $rol);

        $this->loginAs($usuario);

        foreach ($this->rutasDelModulo() as $ruta) {
            // Navegación normal: 403 con el cartel de "activa el módulo", no un 404 seco.
            $this->get($ruta)->assertForbidden()->assertSee('data-pos-modulo-inactivo', false);
            // En JSON el corte es 403 con mensaje legible, para que el front pueda avisarlo.
            $this->getJson($ruta)->assertForbidden();
        }
    }

    public function test_al_activar_el_modulo_las_mismas_rutas_pasan_a_responder_200(): void
    {
        $this->sembrarPermisos();
        $tenant = Tenant::factory()->create();
        $rol = $this->crearRol($tenant, 'Sala', ['ver-pos-sala', 'ver-pos-opciones']);
        $usuario = $this->usuarioConRol($tenant, $rol);

        ConfigPos::guardar($tenant->id, ['hosteleria_activo' => true, 'opciones_activo' => true]);

        $this->loginAs($usuario);

        foreach ($this->rutasDelModulo() as $ruta) {
            $this->get($ruta)->assertOk();
        }
    }

    public function test_el_modulo_activo_pero_sin_permiso_sigue_dando_403(): void
    {
        $this->sembrarPermisos();
        $tenant = Tenant::factory()->create();
        $usuario = $this->usuarioConRol($tenant, $this->crearRol($tenant, 'Solo dashboard', ['ver-dashboard']));

        ConfigPos::guardar($tenant->id, ['hosteleria_activo' => true, 'opciones_activo' => true]);

        $this->loginAs($usuario);

        foreach ($this->rutasDelModulo() as $ruta) {
            $this->get($ruta)->assertForbidden();
        }
    }

    public function test_la_capacidad_de_opciones_apagada_corta_su_ruta_aunque_el_modulo_este_activo(): void
    {
        $this->sembrarPermisos();
        $tenant = Tenant::factory()->create();
        $rol = $this->crearRol($tenant, 'Sala', ['ver-pos-sala', 'ver-pos-opciones']);
        $usuario = $this->usuarioConRol($tenant, $rol);

        // Módulo maestro sí, capacidad de opciones no (FR-004).
        ConfigPos::guardar($tenant->id, ['hosteleria_activo' => true, 'opciones_activo' => false]);

        $this->loginAs($usuario);

        $this->get('/pos/sala')->assertOk();
        $this->get('/pos/opciones')->assertForbidden()->assertSee('data-pos-modulo-inactivo', false);
    }

    public function test_el_menu_no_muestra_las_entradas_del_modulo_cuando_esta_apagado(): void
    {
        $this->sembrarPermisos();
        $tenant = Tenant::factory()->create();
        $rol = $this->crearRol($tenant, 'Sala', ['ver-pos', 'ver-pos-sala', 'ver-pos-opciones']);
        $usuario = $this->usuarioConRol($tenant, $rol);

        $this->loginAs($usuario);
        $this->get('/perfil')->assertOk()->assertDontSee('/pos/sala');

        ConfigPos::guardar($tenant->id, ['hosteleria_activo' => true]);
        MenuTenant::invalidarCache($tenant->id);

        $this->get('/perfil')->assertOk()->assertSee('/pos/sala');
    }
}
