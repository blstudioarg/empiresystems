<?php

namespace Tests\Feature\Traduccion;

use App\Models\Tenant;
use App\Models\User;
use App\Support\ConfigPos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\GestionaRolesDeTenant;
use Tests\TestCase;

/**
 * FR-001/002/003: el idioma del POS se elige en Configuración → POS, en su propio bloque, sin
 * depender del módulo de hostelería; sin elegir nada, el POS está en español.
 */
class IdiomaPosConfiguracionTest extends TestCase
{
    use GestionaRolesDeTenant, RefreshDatabase;

    /** @return array{0: Tenant, 1: User} */
    private function tenantAdmin(): array
    {
        $this->sembrarPermisos();
        $tenant = Tenant::factory()->create();
        $usuario = $this->usuarioConRol($tenant, $this->crearRol($tenant, 'Admin', ['ver-configuracion']));

        return [$tenant, $usuario];
    }

    public function test_un_tenant_sin_la_clave_tiene_el_pos_en_espanol(): void
    {
        $tenant = Tenant::factory()->create();

        $this->assertSame('es', ConfigPos::idioma($tenant->id));
        $this->assertSame('es', ConfigPos::todo($tenant->id)['idioma']);
    }

    public function test_guardar_el_idioma_lo_persiste_y_la_respuesta_lo_incluye(): void
    {
        [$tenant, $usuario] = $this->tenantAdmin();
        $this->loginAs($usuario);

        $this->putJson('/configuracion/pos', ['idioma' => 'zh'])
            ->assertOk()
            ->assertJsonPath('idioma', 'zh')
            ->assertJsonPath('config.idioma', 'zh')
            ->assertJsonStructure(['traducciones_pendientes']);

        $this->assertSame('zh', ConfigPos::idioma($tenant->id));
    }

    public function test_un_idioma_no_permitido_responde_422(): void
    {
        [$tenant, $usuario] = $this->tenantAdmin();
        $this->loginAs($usuario);

        $this->putJson('/configuracion/pos', ['idioma' => 'fr'])->assertStatus(422)->assertJsonValidationErrors('idioma');

        $this->assertSame('es', ConfigPos::idioma($tenant->id));
    }

    public function test_guardar_los_flags_sin_el_campo_no_cambia_el_idioma(): void
    {
        [$tenant, $usuario] = $this->tenantAdmin();
        ConfigPos::guardar($tenant->id, ['idioma' => 'zh']);
        $this->loginAs($usuario);

        $this->putJson('/configuracion/pos', [
            'hosteleria_activo' => 0,
            'opciones_activo' => 0,
            'cobro_dividido_activo' => 0,
            'suplemento_zona_activo' => 0,
            'mesa_olvidada_min' => 45,
        ])->assertOk()->assertJsonPath('config.idioma', 'zh');

        $this->assertSame('zh', ConfigPos::idioma($tenant->id));
    }

    public function test_el_idioma_se_elige_con_el_modulo_de_hosteleria_apagado(): void
    {
        [$tenant, $usuario] = $this->tenantAdmin();
        $this->loginAs($usuario);

        $this->assertFalse(ConfigPos::hosteleriaActivo($tenant->id));
        $this->putJson('/configuracion/pos', ['idioma' => 'zh'])->assertOk();

        $this->assertSame('zh', ConfigPos::idioma($tenant->id));
        $this->assertFalse(ConfigPos::hosteleriaActivo($tenant->id));
    }

    public function test_el_idioma_de_un_tenant_no_afecta_a_otro(): void
    {
        [$tenantA, $usuarioA] = $this->tenantAdmin();
        $tenantB = Tenant::factory()->create();
        $this->loginAs($usuarioA);

        $this->putJson('/configuracion/pos', ['idioma' => 'zh'])->assertOk();

        $this->assertSame('zh', ConfigPos::idioma($tenantA->id));
        $this->assertSame('es', ConfigPos::idioma($tenantB->id));
    }

    public function test_la_pestana_de_configuracion_muestra_el_selector_y_sigue_en_espanol(): void
    {
        [$tenant, $usuario] = $this->tenantAdmin();
        ConfigPos::guardar($tenant->id, ['idioma' => 'zh']);
        $this->loginAs($usuario);

        $this->get('/configuracion')
            ->assertOk()
            ->assertSee('id="pos-idioma-form"', false)
            ->assertSee('<option value="zh" selected', false)
            ->assertDontSee('window.posI18n', false);
    }
}
