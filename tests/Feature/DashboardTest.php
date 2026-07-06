<?php

namespace Tests\Feature;

use App\Models\Articulo;
use App\Models\Cliente;
use App\Models\Compra;
use App\Models\Factura;
use App\Models\Pago;
use App\Models\Tenant;
use App\Models\User;
use App\Services\DashboardEstadisticas;
use App\Support\RangoFechas;
use Carbon\Carbon;
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
        $response->assertSee('Facturado');
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
        $response->assertSee('Facturado');
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
        $resumenA = (new DashboardEstadisticas())->resumen(RangoFechas::mesEnCurso());
        tenancy()->end();

        tenancy()->initialize($tenantB);
        $resumenB = (new DashboardEstadisticas())->resumen(RangoFechas::mesEnCurso());
        tenancy()->end();

        $this->assertEquals(500.0, $resumenA['kpis']['facturado']['valor']);
        $this->assertEquals(9999.0, $resumenB['kpis']['facturado']['valor']);
    }

    public function test_kpis_calculan_facturado_cobrado_pendiente_y_num_facturas_del_mes(): void
    {
        $tenant = Tenant::factory()->create();
        tenancy()->initialize($tenant);

        $cliente = Cliente::factory()->create(['tenant_id' => $tenant->id]);
        $ahora = now();
        $rango = RangoFechas::mesEnCurso($ahora);
        $anterior = $rango->anterior();

        // Rango actual: una factura emitida de 121, con un pago parcial de 50.
        $facturaActual = Factura::factory()->emitida()->create([
            'tenant_id' => $tenant->id,
            'cliente_id' => $cliente->id,
            'fecha_expedicion' => $rango->hasta->toDateString(),
            'total' => 121,
        ]);
        Pago::factory()->create([
            'tenant_id' => $tenant->id,
            'factura_id' => $facturaActual->id,
            'fecha' => $rango->hasta->toDateString(),
            'importe' => 50,
        ]);

        // Periodo anterior (misma duración, justo antes del actual): una factura emitida de 100.
        Factura::factory()->emitida()->create([
            'tenant_id' => $tenant->id,
            'cliente_id' => $cliente->id,
            'fecha_expedicion' => $anterior->hasta->toDateString(),
            'total' => 100,
        ]);

        // Factura en borrador dentro del rango actual: no debe contar.
        Factura::factory()->create([
            'tenant_id' => $tenant->id,
            'cliente_id' => $cliente->id,
            'fecha_expedicion' => $rango->hasta->toDateString(),
            'estado' => 'borrador',
            'total' => 777,
        ]);

        $resumen = (new DashboardEstadisticas())->resumen($rango);

        $this->assertEquals(121.0, $resumen['kpis']['facturado']['valor']);
        $this->assertEquals(21.0, $resumen['kpis']['facturado']['variacion_pct']);
        $this->assertEquals(50.0, $resumen['kpis']['cobrado']['valor']);
        $this->assertNull($resumen['kpis']['cobrado']['variacion_pct']);
        // Pendiente de cobro es global (no solo del rango): factura actual (121-50=71) + factura del periodo anterior sin cobrar (100).
        $this->assertEquals(171.0, $resumen['kpis']['pendiente_cobro']['valor']);
        $this->assertEquals(1, $resumen['kpis']['num_facturas']['valor']);
        $this->assertEquals(0.0, $resumen['kpis']['num_facturas']['variacion_pct']);

        tenancy()->end();
    }

    public function test_tenant_sin_facturas_muestra_kpis_en_cero_sin_variacion(): void
    {
        $tenant = Tenant::factory()->create();
        tenancy()->initialize($tenant);

        $resumen = (new DashboardEstadisticas())->resumen(RangoFechas::mesEnCurso());

        $this->assertEquals(0.0, $resumen['kpis']['facturado']['valor']);
        $this->assertNull($resumen['kpis']['facturado']['variacion_pct']);
        $this->assertEquals(0.0, $resumen['kpis']['cobrado']['valor']);
        $this->assertNull($resumen['kpis']['cobrado']['variacion_pct']);
        $this->assertEquals(0.0, $resumen['kpis']['pendiente_cobro']['valor']);
        $this->assertEquals(0, $resumen['kpis']['num_facturas']['valor']);
        $this->assertNull($resumen['kpis']['num_facturas']['variacion_pct']);

        tenancy()->end();
    }

    public function test_serie_facturacion_usa_granularidad_diaria_en_rangos_cortos(): void
    {
        $tenant = Tenant::factory()->create();
        tenancy()->initialize($tenant);

        $cliente = Cliente::factory()->create(['tenant_id' => $tenant->id]);
        $rango = RangoFechas::personalizado(now()->copy()->subDays(4), now());

        Factura::factory()->emitida()->create([
            'tenant_id' => $tenant->id,
            'cliente_id' => $cliente->id,
            'fecha_expedicion' => $rango->hasta->toDateString(),
            'total' => 300,
        ]);

        $resumen = (new DashboardEstadisticas())->resumen($rango);

        $this->assertSame('dia', $rango->granularidad());
        $this->assertCount(5, $resumen['serie_facturacion']);
        $this->assertEquals(300.0, $resumen['serie_facturacion'][4]['facturado']);
        $this->assertEquals(0.0, $resumen['serie_facturacion'][0]['facturado']);

        tenancy()->end();
    }

    public function test_serie_facturacion_usa_granularidad_mensual_en_rangos_largos(): void
    {
        $tenant = Tenant::factory()->create();
        tenancy()->initialize($tenant);

        $cliente = Cliente::factory()->create(['tenant_id' => $tenant->id]);
        $rango = RangoFechas::personalizado(now()->copy()->subDays(90), now());

        Factura::factory()->emitida()->create([
            'tenant_id' => $tenant->id,
            'cliente_id' => $cliente->id,
            'fecha_expedicion' => $rango->hasta->toDateString(),
            'total' => 300,
        ]);

        $resumen = (new DashboardEstadisticas())->resumen($rango);

        $this->assertSame('mes', $rango->granularidad());
        // Un bucket por cada mes que toca el rango (~4 meses), no un punto por día.
        $this->assertLessThanOrEqual(4, count($resumen['serie_facturacion']));
        $this->assertEquals(300.0, collect($resumen['serie_facturacion'])->sum('facturado'));

        tenancy()->end();
    }

    public function test_comparativo_evidencia_brecha_de_cobro(): void
    {
        $tenant = Tenant::factory()->create();
        tenancy()->initialize($tenant);

        $cliente = Cliente::factory()->create(['tenant_id' => $tenant->id]);
        $rango = RangoFechas::mesEnCurso();

        $factura = Factura::factory()->emitida()->create([
            'tenant_id' => $tenant->id,
            'cliente_id' => $cliente->id,
            'fecha_expedicion' => $rango->hasta->toDateString(),
            'total' => 200,
        ]);
        Pago::factory()->create([
            'tenant_id' => $tenant->id,
            'factura_id' => $factura->id,
            'fecha' => $rango->hasta->toDateString(),
            'importe' => 80,
        ]);

        $resumen = (new DashboardEstadisticas())->resumen($rango);

        $ultimoBucket = collect($resumen['comparativo'])->last();
        $this->assertEquals(200.0, $ultimoBucket['facturado']);
        $this->assertEquals(80.0, $ultimoBucket['cobrado']);
        $this->assertGreaterThan($ultimoBucket['cobrado'], $ultimoBucket['facturado']);

        tenancy()->end();
    }

    public function test_variacion_pct_compara_contra_el_periodo_anterior_de_igual_duracion(): void
    {
        $tenant = Tenant::factory()->create();
        tenancy()->initialize($tenant);

        $cliente = Cliente::factory()->create(['tenant_id' => $tenant->id]);

        // Rango de 10 días no alineado con el calendario: el "mes anterior" fijo (Enero) estaría
        // vacío, pero el periodo anterior real (01-10 de marzo) sí tiene facturación.
        $rango = RangoFechas::personalizado(Carbon::parse('2026-03-11'), Carbon::parse('2026-03-20'));

        Factura::factory()->emitida()->create([
            'tenant_id' => $tenant->id,
            'cliente_id' => $cliente->id,
            'fecha_expedicion' => '2026-03-15',
            'total' => 100,
        ]);
        Factura::factory()->emitida()->create([
            'tenant_id' => $tenant->id,
            'cliente_id' => $cliente->id,
            'fecha_expedicion' => '2026-03-05',
            'total' => 50,
        ]);

        $resumen = (new DashboardEstadisticas())->resumen($rango);

        $this->assertEquals(100.0, $resumen['kpis']['facturado']['valor']);
        $this->assertEquals(100.0, $resumen['kpis']['facturado']['variacion_pct']);

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
                'fecha_expedicion' => now()->toDateString(),
            ]);
        }

        $resumen = (new DashboardEstadisticas())->resumen(RangoFechas::mesEnCurso());

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
                'fecha_expedicion' => now()->toDateString(),
                'total' => $i * 100,
            ]);
        }

        $resumen = (new DashboardEstadisticas())->resumen(RangoFechas::mesEnCurso());

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

        $resumen = (new DashboardEstadisticas())->resumen(RangoFechas::mesEnCurso());

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

        $resumen = (new DashboardEstadisticas())->resumen(RangoFechas::mesEnCurso());

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

        $rango = RangoFechas::personalizado(now()->copy()->subDays(30), now());
        $resumen = (new DashboardEstadisticas())->resumen($rango);

        $this->assertCount(8, $resumen['facturas_recientes']);
        $this->assertEquals(now()->toDateString(), $resumen['facturas_recientes'][0]['fecha_expedicion']);

        tenancy()->end();
    }

    // --- US1: facturado neto (sin doble conteo de rectificativas) ---

    private function crearOriginalRectificada(Tenant $tenant, Cliente $cliente, float $totalOriginal, string $fecha): Factura
    {
        return Factura::factory()->emitida()->create([
            'tenant_id' => $tenant->id,
            'cliente_id' => $cliente->id,
            'estado' => 'rectificada',
            'fecha_expedicion' => $fecha,
            'total' => $totalOriginal,
        ]);
    }

    private function crearRectificativa(Tenant $tenant, Cliente $cliente, Factura $original, string $tipoRectificacion, float $total): Factura
    {
        return Factura::factory()->rectificativa()->create([
            'tenant_id' => $tenant->id,
            'cliente_id' => $cliente->id,
            'factura_rectificada_id' => $original->id,
            'tipo_rectificacion' => $tipoRectificacion,
            'estado' => 'emitida',
            'fecha_expedicion' => $original->fecha_expedicion->toDateString(),
            'total' => $total,
        ]);
    }

    public function test_facturado_neto_sustitucion_no_duplica(): void
    {
        $tenant = Tenant::factory()->create();
        tenancy()->initialize($tenant);

        $cliente = Cliente::factory()->create(['tenant_id' => $tenant->id]);
        $original = $this->crearOriginalRectificada($tenant, $cliente, 100, now()->toDateString());
        $this->crearRectificativa($tenant, $cliente, $original, 'sustitucion', 75);

        $resumen = (new DashboardEstadisticas())->resumen(RangoFechas::mesEnCurso());

        $this->assertEquals(75.0, $resumen['kpis']['facturado']['valor']);

        tenancy()->end();
    }

    public function test_facturado_neto_diferencias_netea(): void
    {
        $tenant = Tenant::factory()->create();
        tenancy()->initialize($tenant);

        $cliente = Cliente::factory()->create(['tenant_id' => $tenant->id]);
        $original = $this->crearOriginalRectificada($tenant, $cliente, 100, now()->toDateString());
        $this->crearRectificativa($tenant, $cliente, $original, 'diferencias', -25);

        $resumen = (new DashboardEstadisticas())->resumen(RangoFechas::mesEnCurso());

        $this->assertEquals(75.0, $resumen['kpis']['facturado']['valor']);

        tenancy()->end();
    }

    public function test_facturado_excluye_borrador_anulada_y_simplificada(): void
    {
        $tenant = Tenant::factory()->create();
        tenancy()->initialize($tenant);

        $cliente = Cliente::factory()->create(['tenant_id' => $tenant->id]);
        $ahora = now()->toDateString();

        Factura::factory()->create([
            'tenant_id' => $tenant->id,
            'cliente_id' => $cliente->id,
            'estado' => 'borrador',
            'fecha_expedicion' => $ahora,
            'total' => 500,
        ]);
        Factura::factory()->create([
            'tenant_id' => $tenant->id,
            'cliente_id' => $cliente->id,
            'estado' => 'anulada',
            'fecha_expedicion' => $ahora,
            'total' => 500,
        ]);
        Factura::factory()->create([
            'tenant_id' => $tenant->id,
            'cliente_id' => $cliente->id,
            'estado' => 'emitida',
            'tipo' => 'simplificada',
            'fecha_expedicion' => $ahora,
            'total' => 500,
        ]);

        $resumen = (new DashboardEstadisticas())->resumen(RangoFechas::mesEnCurso());

        $this->assertEquals(0.0, $resumen['kpis']['facturado']['valor']);

        tenancy()->end();
    }

    public function test_top_clientes_usa_facturado_neto_sin_inflar_por_sustitucion(): void
    {
        $tenant = Tenant::factory()->create();
        tenancy()->initialize($tenant);

        $cliente = Cliente::factory()->create(['tenant_id' => $tenant->id, 'razon_social' => 'Cliente Neto']);
        $original = $this->crearOriginalRectificada($tenant, $cliente, 100, now()->toDateString());
        $original->update(['cliente_razon_social' => 'Cliente Neto']);
        $this->crearRectificativa($tenant, $cliente, $original, 'sustitucion', 75);

        $resumen = (new DashboardEstadisticas())->resumen(RangoFechas::mesEnCurso());

        $this->assertCount(1, $resumen['top_clientes']);
        $this->assertEquals(75.0, $resumen['top_clientes'][0]['total_facturado']);

        tenancy()->end();
    }

    public function test_facturado_neto_aisla_datos_entre_tenants(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        tenancy()->initialize($tenantA);
        $clienteA = Cliente::factory()->create(['tenant_id' => $tenantA->id]);
        $originalA = $this->crearOriginalRectificada($tenantA, $clienteA, 100, now()->toDateString());
        $this->crearRectificativa($tenantA, $clienteA, $originalA, 'sustitucion', 75);
        tenancy()->end();

        tenancy()->initialize($tenantB);
        $clienteB = Cliente::factory()->create(['tenant_id' => $tenantB->id]);
        Factura::factory()->emitida()->create([
            'tenant_id' => $tenantB->id,
            'cliente_id' => $clienteB->id,
            'fecha_expedicion' => now()->toDateString(),
            'total' => 300,
        ]);
        tenancy()->end();

        tenancy()->initialize($tenantA);
        $resumenA = (new DashboardEstadisticas())->resumen(RangoFechas::mesEnCurso());
        tenancy()->end();

        tenancy()->initialize($tenantB);
        $resumenB = (new DashboardEstadisticas())->resumen(RangoFechas::mesEnCurso());
        tenancy()->end();

        $this->assertEquals(75.0, $resumenA['kpis']['facturado']['valor']);
        $this->assertEquals(300.0, $resumenB['kpis']['facturado']['valor']);
    }

    // --- US2: filtro por rango de fechas (query params de la ruta) ---

    private function crearUsuarioLogueado(Tenant $tenant): User
    {
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $this->loginAs($user);

        return $user;
    }

    public function test_ruta_sin_parametros_usa_mes_en_curso(): void
    {
        $this->travelTo(Carbon::parse('2026-08-15'));

        $tenant = Tenant::factory()->create();
        tenancy()->initialize($tenant);
        $cliente = Cliente::factory()->create(['tenant_id' => $tenant->id]);
        $this->crearUsuarioLogueado($tenant);

        Factura::factory()->emitida()->create([
            'tenant_id' => $tenant->id,
            'cliente_id' => $cliente->id,
            'fecha_expedicion' => '2026-08-10',
            'total' => 321,
        ]);
        Factura::factory()->emitida()->create([
            'tenant_id' => $tenant->id,
            'cliente_id' => $cliente->id,
            'fecha_expedicion' => '2026-07-20',
            'total' => 999,
        ]);
        tenancy()->end();

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('321,00');
        $response->assertDontSee('999,00');
    }

    public function test_ruta_preset_trimestre_refleja_el_trimestre_en_curso(): void
    {
        $this->travelTo(Carbon::parse('2026-08-15'));

        $tenant = Tenant::factory()->create();
        tenancy()->initialize($tenant);
        $cliente = Cliente::factory()->create(['tenant_id' => $tenant->id]);
        $this->crearUsuarioLogueado($tenant);

        Factura::factory()->emitida()->create([
            'tenant_id' => $tenant->id,
            'cliente_id' => $cliente->id,
            'fecha_expedicion' => '2026-07-05',
            'total' => 650,
        ]);
        Factura::factory()->emitida()->create([
            'tenant_id' => $tenant->id,
            'cliente_id' => $cliente->id,
            'fecha_expedicion' => '2026-06-20',
            'total' => 999,
        ]);
        tenancy()->end();

        $response = $this->get('/?preset=trimestre');

        $response->assertOk();
        $response->assertSee('650,00');
        $response->assertDontSee('999,00');
    }

    public function test_ruta_preset_anio_refleja_el_anio_en_curso(): void
    {
        $this->travelTo(Carbon::parse('2026-08-15'));

        $tenant = Tenant::factory()->create();
        tenancy()->initialize($tenant);
        $cliente = Cliente::factory()->create(['tenant_id' => $tenant->id]);
        $this->crearUsuarioLogueado($tenant);

        Factura::factory()->emitida()->create([
            'tenant_id' => $tenant->id,
            'cliente_id' => $cliente->id,
            'fecha_expedicion' => '2026-02-10',
            'total' => 750,
        ]);
        Factura::factory()->emitida()->create([
            'tenant_id' => $tenant->id,
            'cliente_id' => $cliente->id,
            'fecha_expedicion' => '2025-12-20',
            'total' => 999,
        ]);
        tenancy()->end();

        $response = $this->get('/?preset=anio');

        $response->assertOk();
        $response->assertSee('750,00');
        $response->assertDontSee('999,00');
    }

    public function test_ruta_personalizado_valido_acota_al_rango(): void
    {
        $this->travelTo(Carbon::parse('2026-08-15'));

        $tenant = Tenant::factory()->create();
        tenancy()->initialize($tenant);
        $cliente = Cliente::factory()->create(['tenant_id' => $tenant->id]);
        $this->crearUsuarioLogueado($tenant);

        Factura::factory()->emitida()->create([
            'tenant_id' => $tenant->id,
            'cliente_id' => $cliente->id,
            'fecha_expedicion' => '2026-03-05',
            'total' => 430,
        ]);
        Factura::factory()->emitida()->create([
            'tenant_id' => $tenant->id,
            'cliente_id' => $cliente->id,
            'fecha_expedicion' => '2026-03-15',
            'total' => 999,
        ]);
        tenancy()->end();

        $response = $this->get('/?preset=personalizado&desde=2026-03-01&hasta=2026-03-10');

        $response->assertOk();
        $response->assertSee('430,00');
        $response->assertDontSee('999,00');
    }

    public function test_ruta_personalizado_con_hasta_anterior_a_desde_cae_a_mes_en_curso_con_aviso(): void
    {
        $this->travelTo(Carbon::parse('2026-08-15'));

        $tenant = Tenant::factory()->create();
        tenancy()->initialize($tenant);
        $cliente = Cliente::factory()->create(['tenant_id' => $tenant->id]);
        $this->crearUsuarioLogueado($tenant);

        Factura::factory()->emitida()->create([
            'tenant_id' => $tenant->id,
            'cliente_id' => $cliente->id,
            'fecha_expedicion' => '2026-08-10',
            'total' => 555,
        ]);
        tenancy()->end();

        $response = $this->get('/?preset=personalizado&desde=2026-05-01&hasta=2026-04-01');

        $response->assertOk();
        $response->assertSee('555,00');
        $response->assertSessionHas('warning');
    }

    public function test_ruta_preset_desconocido_cae_a_mes_en_curso_con_aviso(): void
    {
        $this->travelTo(Carbon::parse('2026-08-15'));

        $tenant = Tenant::factory()->create();
        tenancy()->initialize($tenant);
        $cliente = Cliente::factory()->create(['tenant_id' => $tenant->id]);
        $this->crearUsuarioLogueado($tenant);

        Factura::factory()->emitida()->create([
            'tenant_id' => $tenant->id,
            'cliente_id' => $cliente->id,
            'fecha_expedicion' => '2026-08-10',
            'total' => 555,
        ]);
        tenancy()->end();

        $response = $this->get('/?preset=quincena');

        $response->assertOk();
        $response->assertSee('555,00');
        $response->assertSessionHas('warning');
    }

    // --- US3: gastos/resultado, IVA repercutido vs. soportado, ventas POS ---

    public function test_gastos_suma_compras_confirmadas_del_rango_y_resultado_es_facturado_menos_gastos(): void
    {
        $tenant = Tenant::factory()->create();
        tenancy()->initialize($tenant);

        $cliente = Cliente::factory()->create(['tenant_id' => $tenant->id]);
        $rango = RangoFechas::mesEnCurso();

        Factura::factory()->emitida()->create([
            'tenant_id' => $tenant->id,
            'cliente_id' => $cliente->id,
            'fecha_expedicion' => $rango->hasta->toDateString(),
            'total' => 500,
        ]);

        Compra::factory()->confirmada()->create([
            'tenant_id' => $tenant->id,
            'fecha' => $rango->hasta->toDateString(),
            'total' => 200,
        ]);
        // Borrador y anulada no cuentan como gasto real.
        Compra::factory()->create([
            'tenant_id' => $tenant->id,
            'fecha' => $rango->hasta->toDateString(),
            'estado' => 'borrador',
            'total' => 999,
        ]);
        Compra::factory()->create([
            'tenant_id' => $tenant->id,
            'fecha' => $rango->hasta->toDateString(),
            'estado' => 'anulada',
            'total' => 999,
        ]);

        $resumen = (new DashboardEstadisticas())->resumen($rango);

        $this->assertEquals(200.0, $resumen['kpis']['gastos']['valor']);
        $this->assertEquals(300.0, $resumen['kpis']['resultado']['valor']);

        tenancy()->end();
    }

    public function test_impuestos_repercutido_soportado_y_etiqueta_segun_regimen(): void
    {
        $tenant = Tenant::factory()->create(['regimen_impositivo' => 'iva']);
        tenancy()->initialize($tenant);

        $cliente = Cliente::factory()->create(['tenant_id' => $tenant->id]);
        $rango = RangoFechas::mesEnCurso();

        Factura::factory()->emitida()->create([
            'tenant_id' => $tenant->id,
            'cliente_id' => $cliente->id,
            'fecha_expedicion' => $rango->hasta->toDateString(),
            'total' => 121,
            'cuota_impuesto_total' => 21,
        ]);

        Compra::factory()->confirmada()->create([
            'tenant_id' => $tenant->id,
            'fecha' => $rango->hasta->toDateString(),
            'cuota_impuesto_total' => 42,
        ]);

        $resumen = (new DashboardEstadisticas())->resumen($rango);

        $this->assertEquals(21.0, $resumen['impuestos']['repercutido']);
        $this->assertEquals(42.0, $resumen['impuestos']['soportado']);
        $this->assertEquals('IVA', $resumen['impuestos']['etiqueta']);

        tenancy()->end();
    }

    public function test_ventas_pos_suma_simplificadas_del_rango_sin_entrar_en_facturado(): void
    {
        $tenant = Tenant::factory()->create();
        tenancy()->initialize($tenant);

        $cliente = Cliente::factory()->create(['tenant_id' => $tenant->id]);
        $rango = RangoFechas::mesEnCurso();

        Factura::factory()->emitida()->create([
            'tenant_id' => $tenant->id,
            'cliente_id' => $cliente->id,
            'tipo' => 'simplificada',
            'fecha_expedicion' => $rango->hasta->toDateString(),
            'total' => 60,
        ]);
        Factura::factory()->emitida()->create([
            'tenant_id' => $tenant->id,
            'cliente_id' => $cliente->id,
            'fecha_expedicion' => $rango->hasta->toDateString(),
            'total' => 200,
        ]);

        $resumen = (new DashboardEstadisticas())->resumen($rango);

        $this->assertEquals(60.0, $resumen['kpis']['ventas_pos']['valor']);
        $this->assertEquals(200.0, $resumen['kpis']['facturado']['valor']);

        tenancy()->end();
    }

    // --- Polish: la cifra "Facturado" del dashboard usa la misma lógica de neteo que la card
    // "Importe total" del listado de facturas (FacturaController::index) para el mismo periodo ---

    public function test_facturado_del_dashboard_coincide_con_importe_total_de_facturas_index(): void
    {
        $tenant = Tenant::factory()->create();
        tenancy()->initialize($tenant);

        $cliente = Cliente::factory()->create(['tenant_id' => $tenant->id]);
        $rango = RangoFechas::mesEnCurso();

        // Directa.
        Factura::factory()->emitida()->create([
            'tenant_id' => $tenant->id,
            'cliente_id' => $cliente->id,
            'fecha_expedicion' => $rango->hasta->toDateString(),
            'total' => 150,
        ]);
        // Rectificada por sustitución.
        $original = $this->crearOriginalRectificada($tenant, $cliente, 100, $rango->hasta->toDateString());
        $this->crearRectificativa($tenant, $cliente, $original, 'sustitucion', 75);
        // Simplificada: no debe aportar ni aquí ni en el listado de facturas.
        Factura::factory()->emitida()->create([
            'tenant_id' => $tenant->id,
            'cliente_id' => $cliente->id,
            'tipo' => 'simplificada',
            'fecha_expedicion' => $rango->hasta->toDateString(),
            'total' => 60,
        ]);

        $resumen = (new DashboardEstadisticas())->resumen($rango);

        // Misma lógica que FacturaController::index (totales.importe_total): no simplificadas,
        // no rectificativas, estados facturados, totalCobrable().
        $importeTotalListado = Factura::query()
            ->where('tipo', '!=', 'simplificada')
            ->get()
            ->reject(fn (Factura $f) => $f->es_rectificativa)
            ->filter(fn (Factura $f) => in_array($f->estado->value, ['emitida', 'pagada', 'vencida', 'rectificada'], true))
            ->sum(fn (Factura $f) => $f->totalCobrable());

        $this->assertEquals(round($importeTotalListado, 2), $resumen['kpis']['facturado']['valor']);

        tenancy()->end();
    }

    // --- Filtro por AJAX: la ruta responde JSON (html parcial + gráficos + rango) cuando el
    // cliente pide Accept: application/json, para recargar sin navegar de página ---

    public function test_ruta_responde_json_con_html_parcial_cuando_se_pide_accept_json(): void
    {
        $this->travelTo(Carbon::parse('2026-08-15'));

        $tenant = Tenant::factory()->create();
        tenancy()->initialize($tenant);
        $cliente = Cliente::factory()->create(['tenant_id' => $tenant->id]);
        $this->crearUsuarioLogueado($tenant);

        Factura::factory()->emitida()->create([
            'tenant_id' => $tenant->id,
            'cliente_id' => $cliente->id,
            'fecha_expedicion' => '2026-07-05',
            'total' => 650,
        ]);
        tenancy()->end();

        $response = $this->get('/?preset=trimestre', ['Accept' => 'application/json']);

        $response->assertOk();
        $response->assertJsonStructure(['html', 'rango' => ['preset', 'desde', 'hasta', 'etiqueta'], 'graficos', 'aviso']);
        $response->assertJsonPath('rango.preset', 'trimestre');
        $response->assertJsonPath('aviso', null);
        $this->assertStringContainsString('650,00', $response->json('html'));
    }

    public function test_ruta_json_con_rango_invalido_devuelve_aviso_sin_flash_de_sesion(): void
    {
        $tenant = Tenant::factory()->create();
        $this->crearUsuarioLogueado($tenant);

        $response = $this->get('/?preset=personalizado&desde=2026-05-01&hasta=2026-04-01', ['Accept' => 'application/json']);

        $response->assertOk();
        $response->assertJsonPath('rango.preset', 'mes');
        $response->assertJsonPath('aviso', 'El rango de fechas indicado no es válido. Mostrando el mes en curso.');
        $response->assertSessionMissing('warning');
    }
}
