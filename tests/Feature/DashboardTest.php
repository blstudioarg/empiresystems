<?php

namespace Tests\Feature;

use App\Models\Articulo;
use App\Models\Cliente;
use App\Models\Factura;
use App\Models\Pago;
use App\Models\Tenant;
use App\Models\User;
use App\Services\DashboardEstadisticas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_home_renderiza_sin_datos(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);

        $this->loginAs($user);
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Facturado este mes');
    }

    public function test_la_home_renderiza_con_datos_completos(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $cliente = Cliente::factory()->create(['tenant_id' => $tenant->id]);

        $factura = Factura::factory()->emitida()->create([
            'tenant_id' => $tenant->id,
            'cliente_id' => $cliente->id,
            'fecha_expedicion' => now()->toDateString(),
            'total' => 121,
        ]);
        Pago::factory()->create([
            'tenant_id' => $tenant->id,
            'factura_id' => $factura->id,
            'fecha' => now()->toDateString(),
            'importe' => 50,
        ]);
        Articulo::factory()->create([
            'tenant_id' => $tenant->id,
            'gestion_stock' => true,
            'stock_actual' => 1,
            'stock_minimo' => 5,
        ]);

        $this->loginAs($user);
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Facturado este mes');
        $response->assertSee('Alertas de stock');
    }

    public function test_resumen_aisla_datos_entre_tenants(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        tenancy()->initialize($tenantA);
        $clienteA = Cliente::factory()->create(['tenant_id' => $tenantA->id]);
        Factura::factory()->emitida()->create([
            'tenant_id' => $tenantA->id,
            'cliente_id' => $clienteA->id,
            'fecha_expedicion' => now()->toDateString(),
            'total' => 500,
        ]);
        tenancy()->end();

        tenancy()->initialize($tenantB);
        $clienteB = Cliente::factory()->create(['tenant_id' => $tenantB->id]);
        Factura::factory()->emitida()->create([
            'tenant_id' => $tenantB->id,
            'cliente_id' => $clienteB->id,
            'fecha_expedicion' => now()->toDateString(),
            'total' => 9999,
        ]);
        tenancy()->end();

        tenancy()->initialize($tenantA);
        $resumenA = (new DashboardEstadisticas())->resumen();
        tenancy()->end();

        tenancy()->initialize($tenantB);
        $resumenB = (new DashboardEstadisticas())->resumen();
        tenancy()->end();

        $this->assertEquals(500.0, $resumenA['kpis']['facturado_mes']['valor']);
        $this->assertEquals(9999.0, $resumenB['kpis']['facturado_mes']['valor']);
    }

    public function test_kpis_calculan_facturado_cobrado_pendiente_y_num_facturas_del_mes(): void
    {
        $tenant = Tenant::factory()->create();
        tenancy()->initialize($tenant);

        $cliente = Cliente::factory()->create(['tenant_id' => $tenant->id]);
        $ahora = now();
        $mesAnterior = $ahora->copy()->subMonthNoOverflow();

        // Mes actual: una factura emitida de 121, con un pago parcial de 50.
        $facturaActual = Factura::factory()->emitida()->create([
            'tenant_id' => $tenant->id,
            'cliente_id' => $cliente->id,
            'fecha_expedicion' => $ahora->toDateString(),
            'total' => 121,
        ]);
        Pago::factory()->create([
            'tenant_id' => $tenant->id,
            'factura_id' => $facturaActual->id,
            'fecha' => $ahora->toDateString(),
            'importe' => 50,
        ]);

        // Mes anterior: una factura emitida de 100.
        Factura::factory()->emitida()->create([
            'tenant_id' => $tenant->id,
            'cliente_id' => $cliente->id,
            'fecha_expedicion' => $mesAnterior->toDateString(),
            'total' => 100,
        ]);

        // Factura en borrador dentro del mes actual: no debe contar.
        Factura::factory()->create([
            'tenant_id' => $tenant->id,
            'cliente_id' => $cliente->id,
            'fecha_expedicion' => $ahora->toDateString(),
            'estado' => 'borrador',
            'total' => 777,
        ]);

        $resumen = (new DashboardEstadisticas())->resumen();

        $this->assertEquals(121.0, $resumen['kpis']['facturado_mes']['valor']);
        $this->assertEquals(21.0, $resumen['kpis']['facturado_mes']['variacion_pct']);
        $this->assertEquals(50.0, $resumen['kpis']['cobrado_mes']['valor']);
        $this->assertNull($resumen['kpis']['cobrado_mes']['variacion_pct']);
        // Pendiente de cobro es global (no solo del mes): factura actual (121-50=71) + factura del mes anterior sin cobrar (100).
        $this->assertEquals(171.0, $resumen['kpis']['pendiente_cobro']['valor']);
        $this->assertEquals(1, $resumen['kpis']['num_facturas_mes']['valor']);
        $this->assertEquals(0.0, $resumen['kpis']['num_facturas_mes']['variacion_pct']);

        tenancy()->end();
    }

    public function test_tenant_sin_facturas_muestra_kpis_en_cero_sin_variacion(): void
    {
        $tenant = Tenant::factory()->create();
        tenancy()->initialize($tenant);

        $resumen = (new DashboardEstadisticas())->resumen();

        $this->assertEquals(0.0, $resumen['kpis']['facturado_mes']['valor']);
        $this->assertNull($resumen['kpis']['facturado_mes']['variacion_pct']);
        $this->assertEquals(0.0, $resumen['kpis']['cobrado_mes']['valor']);
        $this->assertNull($resumen['kpis']['cobrado_mes']['variacion_pct']);
        $this->assertEquals(0.0, $resumen['kpis']['pendiente_cobro']['valor']);
        $this->assertEquals(0, $resumen['kpis']['num_facturas_mes']['valor']);
        $this->assertNull($resumen['kpis']['num_facturas_mes']['variacion_pct']);

        tenancy()->end();
    }

    public function test_serie_facturacion_12_meses_incluye_meses_sin_actividad(): void
    {
        $tenant = Tenant::factory()->create();
        tenancy()->initialize($tenant);

        $cliente = Cliente::factory()->create(['tenant_id' => $tenant->id]);
        $ahora = now();

        Factura::factory()->emitida()->create([
            'tenant_id' => $tenant->id,
            'cliente_id' => $cliente->id,
            'fecha_expedicion' => $ahora->toDateString(),
            'total' => 300,
        ]);
        Factura::factory()->emitida()->create([
            'tenant_id' => $tenant->id,
            'cliente_id' => $cliente->id,
            'fecha_expedicion' => $ahora->copy()->subMonthsNoOverflow(5)->toDateString(),
            'total' => 200,
        ]);

        $resumen = (new DashboardEstadisticas())->resumen();

        $this->assertCount(12, $resumen['serie_facturacion_12_meses']);
        $this->assertEquals(300.0, $resumen['serie_facturacion_12_meses'][11]['facturado']);
        // Mes sin actividad (hace 1 mes, ninguna factura creada ahí).
        $this->assertEquals(0.0, $resumen['serie_facturacion_12_meses'][10]['facturado']);

        tenancy()->end();
    }

    public function test_comparativo_6_meses_evidencia_brecha_de_cobro(): void
    {
        $tenant = Tenant::factory()->create();
        tenancy()->initialize($tenant);

        $cliente = Cliente::factory()->create(['tenant_id' => $tenant->id]);
        $ahora = now();

        $factura = Factura::factory()->emitida()->create([
            'tenant_id' => $tenant->id,
            'cliente_id' => $cliente->id,
            'fecha_expedicion' => $ahora->toDateString(),
            'total' => 200,
        ]);
        Pago::factory()->create([
            'tenant_id' => $tenant->id,
            'factura_id' => $factura->id,
            'fecha' => $ahora->toDateString(),
            'importe' => 80,
        ]);

        $resumen = (new DashboardEstadisticas())->resumen();

        $this->assertCount(6, $resumen['comparativo_6_meses']);
        $ultimoMes = $resumen['comparativo_6_meses'][5];
        $this->assertEquals(200.0, $ultimoMes['facturado']);
        $this->assertEquals(80.0, $ultimoMes['cobrado']);
        $this->assertGreaterThan($ultimoMes['cobrado'], $ultimoMes['facturado']);

        tenancy()->end();
    }

    public function test_distribucion_estados_cuenta_cada_estado_del_enum(): void
    {
        $tenant = Tenant::factory()->create();
        tenancy()->initialize($tenant);

        $cliente = Cliente::factory()->create(['tenant_id' => $tenant->id]);

        foreach (['borrador', 'emitida', 'pagada', 'vencida', 'anulada', 'rectificada'] as $estado) {
            Factura::factory()->create([
                'tenant_id' => $tenant->id,
                'cliente_id' => $cliente->id,
                'estado' => $estado,
            ]);
        }

        $resumen = (new DashboardEstadisticas())->resumen();

        $conteos = collect($resumen['distribucion_estados'])->pluck('cantidad', 'estado');

        foreach (['borrador', 'emitida', 'pagada', 'vencida', 'anulada', 'rectificada'] as $estado) {
            $this->assertEquals(1, $conteos[$estado]);
        }

        tenancy()->end();
    }

    public function test_top_clientes_devuelve_maximo_5_ordenados_por_total(): void
    {
        $tenant = Tenant::factory()->create();
        tenancy()->initialize($tenant);

        foreach (range(1, 6) as $i) {
            $cliente = Cliente::factory()->create(['tenant_id' => $tenant->id, 'razon_social' => "Cliente $i"]);
            Factura::factory()->emitida()->create([
                'tenant_id' => $tenant->id,
                'cliente_id' => $cliente->id,
                'cliente_razon_social' => "Cliente $i",
                'total' => $i * 100,
            ]);
        }

        $resumen = (new DashboardEstadisticas())->resumen();

        $this->assertCount(5, $resumen['top_clientes']);
        $this->assertEquals('Cliente 6', $resumen['top_clientes'][0]['nombre']);
        $this->assertEquals(600.0, $resumen['top_clientes'][0]['total_facturado']);
        $this->assertEquals('Cliente 2', $resumen['top_clientes'][4]['nombre']);

        tenancy()->end();
    }

    public function test_alertas_stock_detecta_articulo_bajo_minimo(): void
    {
        $tenant = Tenant::factory()->create();
        tenancy()->initialize($tenant);

        $articulo = Articulo::factory()->create([
            'tenant_id' => $tenant->id,
            'gestion_stock' => true,
            'stock_actual' => 2,
            'stock_minimo' => 5,
        ]);

        $resumen = (new DashboardEstadisticas())->resumen();

        $this->assertTrue($resumen['alertas_stock']['gestiona_stock']);
        $this->assertCount(1, $resumen['alertas_stock']['items']);
        $this->assertEquals($articulo->id, $resumen['alertas_stock']['items'][0]['articulo_id']);

        tenancy()->end();
    }

    public function test_alertas_stock_no_aplica_si_tenant_no_gestiona_stock(): void
    {
        $tenant = Tenant::factory()->create();
        tenancy()->initialize($tenant);

        Articulo::factory()->create(['tenant_id' => $tenant->id, 'gestion_stock' => false]);

        $resumen = (new DashboardEstadisticas())->resumen();

        $this->assertFalse($resumen['alertas_stock']['gestiona_stock']);
        $this->assertEmpty($resumen['alertas_stock']['items']);

        tenancy()->end();
    }

    public function test_facturas_recientes_devuelve_maximo_8_ordenadas_por_fecha_descendente(): void
    {
        $tenant = Tenant::factory()->create();
        tenancy()->initialize($tenant);

        $cliente = Cliente::factory()->create(['tenant_id' => $tenant->id]);

        foreach (range(0, 9) as $i) {
            Factura::factory()->emitida()->create([
                'tenant_id' => $tenant->id,
                'cliente_id' => $cliente->id,
                'fecha_expedicion' => now()->subDays($i)->toDateString(),
            ]);
        }

        $resumen = (new DashboardEstadisticas())->resumen();

        $this->assertCount(8, $resumen['facturas_recientes']);
        $this->assertEquals(now()->toDateString(), $resumen['facturas_recientes'][0]['fecha_expedicion']);

        tenancy()->end();
    }
}
