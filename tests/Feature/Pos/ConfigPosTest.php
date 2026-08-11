<?php

namespace Tests\Feature\Pos;

use App\Models\Tenant;
use App\Support\ConfigPos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * FR-002: el módulo de hostelería arranca apagado en TODO tenant, existente o nuevo, y sin
 * necesidad de migrar datos. La garantía es que el default vive en {@see ConfigPos} y no en filas
 * sembradas: un tenant sin ninguna fila en `configuraciones` ya lo tiene apagado.
 */
class ConfigPosTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_tenant_sin_filas_de_configuracion_tiene_el_modulo_apagado(): void
    {
        $tenant = Tenant::factory()->create();

        $this->assertDatabaseMissing('configuraciones', ['tenant_id' => $tenant->id, 'clave' => ConfigPos::CLAVE_HOSTELERIA_ACTIVO]);

        $this->assertFalse(ConfigPos::hosteleriaActivo($tenant->id));
        $this->assertFalse(ConfigPos::opcionesActivo($tenant->id));
        $this->assertFalse(ConfigPos::cobroDivididoActivo($tenant->id));
        $this->assertFalse(ConfigPos::suplementoZonaActivo($tenant->id));
    }

    public function test_el_umbral_de_mesa_olvidada_por_defecto_es_45_minutos(): void
    {
        $tenant = Tenant::factory()->create();

        $this->assertSame(45, ConfigPos::mesaOlvidadaMin($tenant->id));
    }

    public function test_las_capacidades_secundarias_no_cuentan_como_activas_con_el_modulo_apagado(): void
    {
        $tenant = Tenant::factory()->create();

        ConfigPos::guardar($tenant->id, [
            'hosteleria_activo' => false,
            'opciones_activo' => true,
            'cobro_dividido_activo' => true,
            'suplemento_zona_activo' => true,
        ]);

        $this->assertFalse(ConfigPos::opcionesActivo($tenant->id));
        $this->assertFalse(ConfigPos::cobroDivididoActivo($tenant->id));
        $this->assertFalse(ConfigPos::suplementoZonaActivo($tenant->id));

        // Pero el valor guardado sigue ahí: reactivar el módulo las recupera tal cual (FR-007).
        $this->assertTrue(ConfigPos::todo($tenant->id)['opciones_activo']);

        ConfigPos::guardar($tenant->id, ['hosteleria_activo' => true]);

        $this->assertTrue(ConfigPos::opcionesActivo($tenant->id));
    }

    public function test_la_configuracion_de_un_tenant_no_se_lee_desde_otro(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        ConfigPos::guardar($tenantA->id, ['hosteleria_activo' => true, 'mesa_olvidada_min' => 90]);

        $this->assertTrue(ConfigPos::hosteleriaActivo($tenantA->id));
        $this->assertFalse(ConfigPos::hosteleriaActivo($tenantB->id));
        $this->assertSame(90, ConfigPos::mesaOlvidadaMin($tenantA->id));
        $this->assertSame(45, ConfigPos::mesaOlvidadaMin($tenantB->id));
    }
}
