<?php

namespace Tests\Feature;

use App\Enums\EstadoFactura;
use App\Models\Cliente;
use App\Models\Factura;
use App\Models\Pago;
use App\Models\Serie;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\GestionaRolesDeTenant;
use Tests\TestCase;

/**
 * Suite funcional del módulo de Cobros (feature 043): acceso, métricas, filtros, orden,
 * paginación, registro y anulación de cobros. Complementa a ConsultaCobrosParidadTest (Unit,
 * cálculo) y CobrosAislamientoTest (Feature, multi-tenant).
 */
class CobrosModuloTest extends TestCase
{
    use GestionaRolesDeTenant, RefreshDatabase;

    private function crearFactura(Tenant $tenant, Cliente $cliente, Serie $serie, array $overrides = []): Factura
    {
        return Factura::factory()
            ->for($tenant, 'tenant')->for($serie, 'serie')->for($cliente, 'cliente')
            ->emitida()
            ->create($overrides);
    }

    public function test_acceso_exige_el_permiso_ver_cobros(): void
    {
        $this->sembrarPermisos();
        $tenant = Tenant::factory()->create();

        $sinPermiso = $this->usuarioConRol($tenant, $this->crearRol($tenant, 'Sin cobros', ['ver-dashboard']));
        $this->loginAs($sinPermiso);
        $this->get('/cobros')->assertForbidden();
        $this->post('/logout');

        $conPermiso = $this->usuarioConRol($tenant, $this->crearRol($tenant, 'Con cobros', ['ver-cobros']));
        $this->loginAs($conPermiso);
        $this->get('/cobros')->assertOk();
    }

    public function test_las_metricas_del_resumen_coinciden_con_el_dataset(): void
    {
        $this->sembrarPermisos();
        $tenant = Tenant::factory()->create();
        $cliente = Cliente::factory()->for($tenant, 'tenant')->create();
        $serie = Serie::factory()->for($tenant, 'tenant')->create();

        // Pendiente sin cobrar.
        $this->crearFactura($tenant, $cliente, $serie, ['total' => 100, 'fecha_vencimiento' => now()->addDays(10)]);

        // Vencida con saldo.
        $vencida = $this->crearFactura($tenant, $cliente, $serie, ['total' => 200, 'fecha_vencimiento' => now()->subDays(5)]);

        // Cobrada del todo, con el pago dentro del mes en curso.
        $cobrada = $this->crearFactura($tenant, $cliente, $serie, ['total' => 50, 'fecha_vencimiento' => now()->addDays(10)]);
        Pago::factory()->for($tenant, 'tenant')->for($cobrada, 'factura')->create(['importe' => 50, 'fecha' => now()]);

        $usuario = $this->usuarioConRol($tenant, $this->crearRol($tenant, 'Cobros', ['ver-cobros']));
        $this->loginAs($usuario);

        $resumen = $this->getJson('/cobros/resumen')->assertOk()->json();

        // Pendiente: 100 + 200 = 300 (la cobrada no aporta).
        $this->assertEqualsWithDelta(300.0, (float) $resumen['pendiente_total'], 0.001);
        // Vencido: solo la vencida = 200.
        $this->assertEqualsWithDelta(200.0, (float) $resumen['vencido_total'], 0.001);
        // Cobrado en el periodo (mes en curso, por defecto): el pago de 50.
        $this->assertEqualsWithDelta(50.0, (float) $resumen['cobrado_periodo'], 0.001);
        $this->assertSame(2, $resumen['facturas_pendientes']);
    }

    public function test_el_listado_excluye_borradores_e_incluye_la_original_rectificada_con_su_importe_efectivo(): void
    {
        $this->sembrarPermisos();
        $tenant = Tenant::factory()->create();
        $cliente = Cliente::factory()->for($tenant, 'tenant')->create();
        $serie = Serie::factory()->for($tenant, 'tenant')->create();

        $borrador = Factura::factory()->for($tenant, 'tenant')->for($serie, 'serie')->for($cliente, 'cliente')->create();

        $original = $this->crearFactura($tenant, $cliente, $serie, [
            'total' => 100,
            'estado' => EstadoFactura::Rectificada,
        ]);
        $rectificativa = Factura::factory()->for($tenant, 'tenant')->for($serie, 'serie')->for($cliente, 'cliente')
            ->rectificativa()->emitida()
            ->create([
                'total' => 80,
                'factura_rectificada_id' => $original->id,
                'tipo_rectificacion' => 'sustitucion',
            ]);

        $usuario = $this->usuarioConRol($tenant, $this->crearRol($tenant, 'Cobros', ['ver-cobros']));
        $this->loginAs($usuario);

        $payload = $this->getJson('/cobros?draw=1&start=0&length=50')->assertOk()->json();
        $ids = collect($payload['data'])->pluck('id')->all();

        $this->assertNotContains($borrador->id, $ids);
        $this->assertNotContains($rectificativa->id, $ids);
        $this->assertContains($original->id, $ids);

        $filaOriginal = collect($payload['data'])->firstWhere('id', $original->id);
        $this->assertTrue($filaOriginal['es_rectificada']);
        $this->assertSame('80.00', $filaOriginal['total_cobrable']);
        $this->assertSame('100.00', $filaOriginal['total_nominal']);

        // Las fechas viajan en Y-m-d, nunca como datetime ISO crudo (el modelo las castea a
        // Carbon; hay que formatearlas explícitamente en el controller).
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $filaOriginal['fecha_expedicion']);
    }

    public function test_filtros_de_estado_cliente_serie_y_solo_vencidas_se_combinan(): void
    {
        $this->sembrarPermisos();
        $tenant = Tenant::factory()->create();
        $clienteA = Cliente::factory()->for($tenant, 'tenant')->create();
        $clienteB = Cliente::factory()->for($tenant, 'tenant')->create();
        $serie = Serie::factory()->for($tenant, 'tenant')->create();

        $pendienteA = $this->crearFactura($tenant, $clienteA, $serie, ['total' => 100, 'fecha_vencimiento' => now()->addDays(5)]);
        $vencidaA = $this->crearFactura($tenant, $clienteA, $serie, ['total' => 100, 'fecha_vencimiento' => now()->subDays(5)]);
        $this->crearFactura($tenant, $clienteB, $serie, ['total' => 100, 'fecha_vencimiento' => now()->subDays(5)]);

        $usuario = $this->usuarioConRol($tenant, $this->crearRol($tenant, 'Cobros', ['ver-cobros']));
        $this->loginAs($usuario);

        // Solo estado pendiente: las tres (ninguna cobrada).
        $payload = $this->getJson('/cobros?draw=1&start=0&length=50&estado_cobro=pendiente')->assertOk()->json();
        $this->assertCount(3, $payload['data']);

        // Cliente A + solo vencidas: únicamente vencidaA.
        $payload = $this->getJson('/cobros?draw=1&start=0&length=50&cliente_id='.$clienteA->id.'&solo_vencidas=1')->assertOk()->json();
        $ids = collect($payload['data'])->pluck('id')->all();
        $this->assertSame([$vencidaA->id], $ids);
        $this->assertNotContains($pendienteA->id, $ids);
    }

    public function test_busqueda_por_numero_y_por_cliente(): void
    {
        $this->sembrarPermisos();
        $tenant = Tenant::factory()->create();
        $cliente = Cliente::factory()->for($tenant, 'tenant')->create(['nombre' => 'Construcciones Pérez']);
        $serie = Serie::factory()->for($tenant, 'tenant')->create();

        $factura = $this->crearFactura($tenant, $cliente, $serie, ['numero_completo' => 'F-2026-0099']);

        $usuario = $this->usuarioConRol($tenant, $this->crearRol($tenant, 'Cobros', ['ver-cobros']));
        $this->loginAs($usuario);

        $porNumero = $this->getJson('/cobros?draw=1&start=0&length=50&search[value]=0099')->assertOk()->json();
        $this->assertContains($factura->id, collect($porNumero['data'])->pluck('id')->all());

        $porCliente = $this->getJson('/cobros?draw=1&start=0&length=50&search[value]=Pérez')->assertOk()->json();
        $this->assertContains($factura->id, collect($porCliente['data'])->pluck('id')->all());
    }

    public function test_orden_por_saldo_pendiente_y_dias_de_retraso(): void
    {
        $this->sembrarPermisos();
        $tenant = Tenant::factory()->create();
        $cliente = Cliente::factory()->for($tenant, 'tenant')->create();
        $serie = Serie::factory()->for($tenant, 'tenant')->create();

        $bajo = $this->crearFactura($tenant, $cliente, $serie, ['total' => 50]);
        $alto = $this->crearFactura($tenant, $cliente, $serie, ['total' => 500]);

        $usuario = $this->usuarioConRol($tenant, $this->crearRol($tenant, 'Cobros', ['ver-cobros']));
        $this->loginAs($usuario);

        $payload = $this->getJson(
            '/cobros?draw=1&start=0&length=50'
            .'&order[0][column]=6&order[0][dir]=desc'
            .'&columns[6][data]=saldo_pendiente'
        )->assertOk()->json();

        $ids = collect($payload['data'])->pluck('id')->all();
        $this->assertSame($alto->id, $ids[0]);
        $this->assertSame($bajo->id, $ids[1]);
    }

    public function test_registrar_cobro_parcial_y_total_actualiza_estado_y_saldo(): void
    {
        $this->sembrarPermisos();
        $tenant = Tenant::factory()->create();
        $cliente = Cliente::factory()->for($tenant, 'tenant')->create();
        $serie = Serie::factory()->for($tenant, 'tenant')->create();
        $factura = $this->crearFactura($tenant, $cliente, $serie, ['total' => 100]);

        $usuario = $this->usuarioConRol($tenant, $this->crearRol($tenant, 'Cobros', ['ver-cobros']));
        $this->loginAs($usuario);

        $this->postJson("/facturas/{$factura->id}/pagos", [
            'fecha' => now()->toDateString(),
            'importe' => 40,
            'metodo' => 'transferencia',
        ])->assertCreated();

        $factura->refresh();
        $this->assertSame('parcial', $factura->estadoCobro()->value);
        $this->assertEqualsWithDelta(60.0, $factura->saldoPendiente(), 0.001);

        $this->postJson("/facturas/{$factura->id}/pagos", [
            'fecha' => now()->toDateString(),
            'importe' => 60,
            'metodo' => 'transferencia',
        ])->assertCreated();

        $factura->refresh();
        $this->assertSame('cobrada', $factura->estadoCobro()->value);
        $this->assertEqualsWithDelta(0.0, $factura->saldoPendiente(), 0.001);
    }

    public function test_importes_invalidos_no_registran_ningun_pago(): void
    {
        $this->sembrarPermisos();
        $tenant = Tenant::factory()->create();
        $cliente = Cliente::factory()->for($tenant, 'tenant')->create();
        $serie = Serie::factory()->for($tenant, 'tenant')->create();
        $factura = $this->crearFactura($tenant, $cliente, $serie, ['total' => 100]);

        $usuario = $this->usuarioConRol($tenant, $this->crearRol($tenant, 'Cobros', ['ver-cobros']));
        $this->loginAs($usuario);

        $this->postJson("/facturas/{$factura->id}/pagos", [
            'fecha' => now()->toDateString(), 'importe' => 200, 'metodo' => 'transferencia',
        ])->assertStatus(422);

        $this->postJson("/facturas/{$factura->id}/pagos", [
            'fecha' => now()->toDateString(), 'importe' => 0, 'metodo' => 'transferencia',
        ])->assertStatus(422);

        $this->postJson("/facturas/{$factura->id}/pagos", [
            'fecha' => now()->toDateString(), 'importe' => -10, 'metodo' => 'transferencia',
        ])->assertStatus(422);

        $this->assertSame(0, Pago::where('factura_id', $factura->id)->count());
    }

    public function test_anular_el_unico_cobro_devuelve_la_factura_a_pendiente(): void
    {
        $this->sembrarPermisos();
        $tenant = Tenant::factory()->create();
        $cliente = Cliente::factory()->for($tenant, 'tenant')->create();
        $serie = Serie::factory()->for($tenant, 'tenant')->create();
        $factura = $this->crearFactura($tenant, $cliente, $serie, ['total' => 100]);
        $pago = Pago::factory()->for($tenant, 'tenant')->for($factura, 'factura')->create(['importe' => 100]);

        $usuario = $this->usuarioConRol($tenant, $this->crearRol($tenant, 'Cobros', ['ver-cobros']));
        $this->loginAs($usuario);

        $factura->refresh();
        $this->assertSame('cobrada', $factura->estadoCobro()->value);

        $this->postJson("/pagos/{$pago->id}/anular")->assertOk();

        $factura->refresh();
        $this->assertSame('pendiente', $factura->estadoCobro()->value);
        $this->assertEqualsWithDelta(100.0, $factura->saldoPendiente(), 0.001);

        // Reaparece bajo el filtro de pendientes.
        $payload = $this->getJson('/cobros?draw=1&start=0&length=50&estado_cobro=pendiente')->assertOk()->json();
        $this->assertContains($factura->id, collect($payload['data'])->pluck('id')->all());
    }

    public function test_anular_un_cobro_ya_anulado_devuelve_422(): void
    {
        $this->sembrarPermisos();
        $tenant = Tenant::factory()->create();
        $cliente = Cliente::factory()->for($tenant, 'tenant')->create();
        $serie = Serie::factory()->for($tenant, 'tenant')->create();
        $factura = $this->crearFactura($tenant, $cliente, $serie, ['total' => 100]);
        $pago = Pago::factory()->for($tenant, 'tenant')->for($factura, 'factura')->anulado()->create(['importe' => 100]);

        $usuario = $this->usuarioConRol($tenant, $this->crearRol($tenant, 'Cobros', ['ver-cobros']));
        $this->loginAs($usuario);

        $this->postJson("/pagos/{$pago->id}/anular")->assertStatus(422);
    }

    public function test_pdf_url_es_null_sin_permiso_ver_facturas(): void
    {
        $this->sembrarPermisos();
        $tenant = Tenant::factory()->create();
        $cliente = Cliente::factory()->for($tenant, 'tenant')->create();
        $serie = Serie::factory()->for($tenant, 'tenant')->create();
        $this->crearFactura($tenant, $cliente, $serie, ['total' => 100]);

        // Con ver-cobros pero SIN ver-facturas.
        $usuario = $this->usuarioConRol($tenant, $this->crearRol($tenant, 'Solo cobros', ['ver-cobros']));
        $this->loginAs($usuario);

        $payload = $this->getJson('/cobros?draw=1&start=0&length=50')->assertOk()->json();
        foreach ($payload['data'] as $fila) {
            $this->assertNull($fila['pdf_url']);
        }
    }

    public function test_la_pantalla_incluye_la_guia_de_ayuda_propia_de_cobros(): void
    {
        $this->sembrarPermisos();
        $tenant = Tenant::factory()->create();
        $usuario = $this->usuarioConRol($tenant, $this->crearRol($tenant, 'Cobros', ['ver-cobros']));
        $this->loginAs($usuario);

        // Marcador de HTML de la vista, no texto plano (memoria feedback_ayuda_text_collides_assertSee):
        // el modal de ayuda contextual (partials.ayuda-modal) se renderiza siempre desde el layout,
        // y su título toma @section('ayuda-titulo') de la vista actual.
        $this->get('/cobros')->assertOk()
            ->assertSee('id="ayudaContextualModal"', false)
            ->assertSee('<h5 class="ayuda-title">Cobros</h5>', false);
    }

    public function test_facturas_index_oculta_acciones_de_cobro_sin_el_permiso_ver_cobros(): void
    {
        $this->sembrarPermisos();
        $tenant = Tenant::factory()->create();
        $cliente = Cliente::factory()->for($tenant, 'tenant')->create();
        $serie = Serie::factory()->for($tenant, 'tenant')->create();
        $this->crearFactura($tenant, $cliente, $serie, ['total' => 100]);

        // Con ver-facturas pero SIN ver-cobros (T033a): las urls de cobro deben venir nulas.
        $usuario = $this->usuarioConRol($tenant, $this->crearRol($tenant, 'Solo facturas', ['ver-facturas']));
        $this->loginAs($usuario);

        $payload = $this->getJson('/facturas')->assertOk()->json();
        foreach ($payload['data'] as $fila) {
            $this->assertNull($fila['pago_url']);
            $this->assertNull($fila['cobros_url']);
        }
    }
}
