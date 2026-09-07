<?php

namespace Tests\Unit;

use App\Models\Configuracion;
use App\Models\Tenant;
use App\Support\RetencionAsistenteTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Plazo de retención de las conversaciones del asistente (feature 045, FR-016).
 */
class RetencionAsistenteTenantTest extends TestCase
{
    use RefreshDatabase;

    public function test_sin_fila_de_configuracion_devuelve_el_default_de_90_dias(): void
    {
        $tenant = Tenant::factory()->create();

        $this->assertSame(90, RetencionAsistenteTenant::dias($tenant->id));
    }

    public function test_devuelve_el_valor_configurado_por_el_tenant(): void
    {
        $tenant = Tenant::factory()->create();

        Configuracion::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'clave' => RetencionAsistenteTenant::CLAVE_RETENCION_DIAS,
            'valor' => '30',
            'tipo' => 'integer',
            'grupo' => RetencionAsistenteTenant::GRUPO,
        ]);

        $this->assertSame(30, RetencionAsistenteTenant::dias($tenant->id));
    }

    public function test_el_plazo_es_independiente_entre_tenants(): void
    {
        $unTenant = Tenant::factory()->create();
        $otroTenant = Tenant::factory()->create();

        Configuracion::withoutGlobalScopes()->create([
            'tenant_id' => $unTenant->id,
            'clave' => RetencionAsistenteTenant::CLAVE_RETENCION_DIAS,
            'valor' => '15',
            'tipo' => 'integer',
            'grupo' => RetencionAsistenteTenant::GRUPO,
        ]);

        $this->assertSame(15, RetencionAsistenteTenant::dias($unTenant->id));
        $this->assertSame(90, RetencionAsistenteTenant::dias($otroTenant->id));
    }
}
