<?php

namespace Tests\Feature;

use App\Models\Serie;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantSeriesSeedingTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Bug real detectado en revisión de docs vs código (2026-07-04): `Tenant::booted()` solo
     * sembraba la serie "F" (ordinaria). Las series "R" (rectificativa, feature 009) y "S"
     * (simplificada, feature 012) solo existían para el tenant demo vía `SerieSeeder`, nunca para
     * un tenant nuevo dado de alta por el Super Admin. `Serie::activaPorTipo()` usa `firstOrFail()`,
     * así que rectificar una factura o emitir un ticket POS en un tenant nuevo revienta con
     * `ModelNotFoundException`. Este test fija el comportamiento correcto: todo tenant nuevo nace
     * con sus tres series por defecto listas para usar.
     */
    public function test_un_tenant_nuevo_nace_con_las_tres_series_por_defecto(): void
    {
        $tenant = Tenant::factory()->create();

        $series = Serie::where('tenant_id', $tenant->id)->get()->keyBy('codigo');

        $this->assertCount(3, $series, 'El tenant debe nacer con exactamente 3 series: F, R y S.');

        $this->assertTrue($series->has('F'));
        $this->assertEquals('ordinaria', $series['F']->tipo->value);

        $this->assertTrue($series->has('R'));
        $this->assertEquals('rectificativa', $series['R']->tipo->value);

        $this->assertTrue($series->has('S'));
        $this->assertEquals('simplificada', $series['S']->tipo->value);

        foreach ($series as $serie) {
            $this->assertTrue($serie->activa);
            $this->assertEquals(1, $serie->proximo_numero);
            $this->assertEquals('{serie}-{anio}-{numero:0000}', $serie->formato);
            $this->assertNull($serie->ejercicio);
        }
    }

    public function test_series_por_defecto_quedan_aisladas_por_tenant(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $this->assertEquals(3, Serie::where('tenant_id', $tenantA->id)->count());
        $this->assertEquals(3, Serie::where('tenant_id', $tenantB->id)->count());

        $this->assertTrue(
            Serie::where('tenant_id', $tenantA->id)->pluck('id')
                ->intersect(Serie::where('tenant_id', $tenantB->id)->pluck('id'))
                ->isEmpty(),
            'Las series de un tenant no deben compartirse ni solaparse con las de otro.'
        );
    }
}
