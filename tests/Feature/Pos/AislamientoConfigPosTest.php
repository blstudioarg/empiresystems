<?php

namespace Tests\Feature\Pos;

use App\Models\Tenant;
use App\Support\ConfigPos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\GestionaRolesDeTenant;
use Tests\TestCase;

/**
 * Principio I: la configuración POS del tenant A no es visible ni modificable desde el B.
 *
 * El caso interesante no es "no la ve" sino que **escribir desde B no toca la de A**: la ruta es
 * la misma URL para todos los tenants, así que el aislamiento tiene que venir del contexto, no de
 * la ruta.
 */
class AislamientoConfigPosTest extends TestCase
{
    use GestionaRolesDeTenant, RefreshDatabase;

    public function test_la_configuracion_pos_no_se_ve_ni_se_modifica_desde_otro_tenant(): void
    {
        $this->sembrarPermisos();

        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $usuarioA = $this->usuarioConRol($tenantA, $this->crearRol($tenantA, 'Admin A', ['ver-configuracion']));
        $usuarioB = $this->usuarioConRol($tenantB, $this->crearRol($tenantB, 'Admin B', ['ver-configuracion']));

        ConfigPos::guardar($tenantA->id, ['hosteleria_activo' => true, 'mesa_olvidada_min' => 120]);

        // B ve su propia configuración (apagada), no la de A.
        $this->loginAs($usuarioB);
        $this->get('/configuracion')->assertOk()->assertViewHas('posConfig', fn ($config) => $config['hosteleria_activo'] === false
            && $config['mesa_olvidada_min'] === 45);

        // Y al guardar desde B, la de A queda intacta.
        $this->putJson('/configuracion/pos', [
            'hosteleria_activo' => 1,
            'opciones_activo' => 1,
            'cobro_dividido_activo' => 0,
            'suplemento_zona_activo' => 0,
            'mesa_olvidada_min' => 15,
        ])->assertOk();

        $this->assertSame(120, ConfigPos::mesaOlvidadaMin($tenantA->id));
        $this->assertSame(15, ConfigPos::mesaOlvidadaMin($tenantB->id));
        $this->assertFalse(ConfigPos::opcionesActivo($tenantA->id));
        $this->assertTrue(ConfigPos::opcionesActivo($tenantB->id));

        $this->post('/logout');

        $this->loginAs($usuarioA);
        $this->get('/configuracion')->assertOk()->assertViewHas('posConfig', fn ($config) => $config['mesa_olvidada_min'] === 120);
    }
}
