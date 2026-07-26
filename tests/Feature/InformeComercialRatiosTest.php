<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Lead;
use App\Models\Oportunidad;
use App\Models\Presupuesto;
use App\Models\Tenant;
use App\Services\InformeComercial;
use App\Support\AlcanceInformeComercial;
use App\Support\FiltrosInforme;
use App\Support\RangoFechas;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\GestionaRolesDeTenant;
use Tests\TestCase;

class InformeComercialRatiosTest extends TestCase
{
    use GestionaRolesDeTenant, RefreshDatabase;

    private function alcanceTenant(Tenant $tenant): AlcanceInformeComercial
    {
        $this->sembrarPermisos();
        $rol = $this->crearRol($tenant, 'Administrador', \App\Support\CatalogoPermisos::claves());
        $usuario = $this->usuarioConRol($tenant, $rol);

        app(\Spatie\Permission\PermissionRegistrar::class)->setPermissionsTeamId($tenant->getTenantKey());

        return AlcanceInformeComercial::paraUsuario($usuario);
    }

    private function generar(RangoFechas $rango, AlcanceInformeComercial $alcance): array
    {
        return (new InformeComercial())->generar($rango, FiltrosInforme::desdePeticion([]), $alcance);
    }

    public function test_ratios_de_eficiencia_coinciden_con_el_calculo_manual(): void
    {
        $tenant = Tenant::factory()->create();
        tenancy()->initialize($tenant);
        $alcance = $this->alcanceTenant($tenant);
        $cliente = Cliente::factory()->create(['tenant_id' => $tenant->id]);

        $rango = RangoFechas::personalizado(Carbon::parse('2026-06-01'), Carbon::parse('2026-06-30'));

        // 10 leads captados, 3 convertidos -> 30%. Los ciclos de conversión: 4, 6, 8 días -> media 6.
        Lead::factory()->count(7)->create(['tenant_id' => $tenant->id, 'created_at' => '2026-06-01']);
        Lead::factory()->convertido()->create(['tenant_id' => $tenant->id, 'created_at' => '2026-06-01', 'convertido_at' => '2026-06-05']);
        Lead::factory()->convertido()->create(['tenant_id' => $tenant->id, 'created_at' => '2026-06-01', 'convertido_at' => '2026-06-07']);
        Lead::factory()->convertido()->create(['tenant_id' => $tenant->id, 'created_at' => '2026-06-01', 'convertido_at' => '2026-06-09']);

        // 5 ganadas (ciclo 10 días, importe 1000) + 5 perdidas (ciclo 20 días) -> 50% ganadas.
        Oportunidad::factory()->ganada()->count(5)->create([
            'tenant_id' => $tenant->id, 'cliente_id' => $cliente->id, 'created_at' => '2026-06-01',
            'importe_estimado' => 1000, 'cerrada_at' => '2026-06-11',
        ]);
        Oportunidad::factory()->perdida()->count(5)->create([
            'tenant_id' => $tenant->id, 'cliente_id' => $cliente->id, 'created_at' => '2026-06-01', 'cerrada_at' => '2026-06-21',
        ]);

        // 10 presupuestos emitidos, 4 aceptados (2 aceptado + 2 facturado) -> 40%. 2 facturados -> conversión 20%.
        Presupuesto::factory()->count(6)->create(['tenant_id' => $tenant->id, 'cliente_id' => $cliente->id, 'fecha_emision' => '2026-06-15']);
        Presupuesto::factory()->aceptado()->count(2)->create(['tenant_id' => $tenant->id, 'cliente_id' => $cliente->id, 'fecha_emision' => '2026-06-16']);

        $factura1 = \App\Models\Factura::factory()->emitida()->create(['tenant_id' => $tenant->id, 'cliente_id' => $cliente->id, 'fecha_expedicion' => '2026-06-17']);
        $factura2 = \App\Models\Factura::factory()->emitida()->create(['tenant_id' => $tenant->id, 'cliente_id' => $cliente->id, 'fecha_expedicion' => '2026-06-17']);
        Presupuesto::factory()->facturado()->create(['tenant_id' => $tenant->id, 'cliente_id' => $cliente->id, 'fecha_emision' => '2026-06-17', 'convertido_a_factura_id' => $factura1->id]);
        Presupuesto::factory()->facturado()->create(['tenant_id' => $tenant->id, 'cliente_id' => $cliente->id, 'fecha_emision' => '2026-06-17', 'convertido_a_factura_id' => $factura2->id]);

        $datos = $this->generar($rango, $alcance);

        $this->assertEquals(30.0, $datos['ratios']['conversion_lead_cliente']);
        $this->assertEquals(6.0, $datos['ratios']['ciclo_medio_lead_dias']);
        $this->assertEquals(50.0, $datos['ratios']['oportunidades_ganadas']);
        $this->assertEquals(1000.0, $datos['ratios']['importe_medio_ganada']);
        $this->assertEquals(15.0, $datos['ratios']['ciclo_medio_oportunidad_dias']);
        $this->assertEquals(40.0, $datos['ratios']['aceptacion_presupuestos']);
        $this->assertEquals(20.0, $datos['ratios']['conversion_presupuesto_factura']);

        tenancy()->end();
    }

    public function test_denominador_cero_devuelve_null_nunca_cero_ni_excepcion(): void
    {
        $tenant = Tenant::factory()->create();
        tenancy()->initialize($tenant);
        $alcance = $this->alcanceTenant($tenant);

        $rango = RangoFechas::personalizado(Carbon::parse('2026-06-01'), Carbon::parse('2026-06-30'));

        $datos = $this->generar($rango, $alcance);

        $this->assertSame(0, $datos['indicadores']['leads_captados']);
        $this->assertNull($datos['ratios']['conversion_lead_cliente']);
        $this->assertNull($datos['ratios']['oportunidades_ganadas']);
        $this->assertNull($datos['ratios']['aceptacion_presupuestos']);
        $this->assertNull($datos['ratios']['conversion_presupuesto_factura']);
        $this->assertNull($datos['ratios']['importe_medio_ganada']);
        $this->assertNull($datos['ratios']['ciclo_medio_lead_dias']);
        $this->assertNull($datos['ratios']['ciclo_medio_oportunidad_dias']);

        tenancy()->end();
    }
}
