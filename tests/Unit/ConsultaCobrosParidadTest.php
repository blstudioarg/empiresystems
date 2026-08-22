<?php

namespace Tests\Unit;

use App\Enums\EstadoFactura;
use App\Enums\TipoFactura;
use App\Enums\TipoRectificacion;
use App\Models\Cliente;
use App\Models\Factura;
use App\Models\Pago;
use App\Models\Serie;
use App\Models\Tenant;
use App\Support\ConsultaCobros;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Test de paridad (feature 043, research D2, Principio IV): la fila que devuelve el espejo SQL
 * de App\Support\ConsultaCobros debe coincidir al céntimo con lo que devuelven los métodos del
 * modelo Factura (totalCobrable, montoCobrado, saldoPendiente, estadoCobro) para el mismo
 * conjunto de facturas. Se escribe ANTES de que ConsultaCobros exista: debe fallar primero.
 */
class ConsultaCobrosParidadTest extends TestCase
{
    use RefreshDatabase;

    private function crearFactura(Tenant $tenant, Cliente $cliente, Serie $serie, array $overrides = []): Factura
    {
        return Factura::factory()
            ->for($tenant, 'tenant')
            ->create(array_merge([
                'cliente_id' => $cliente->id,
                'serie_id' => $serie->id,
                'estado' => EstadoFactura::Emitida,
                'tipo' => TipoFactura::Ordinaria,
                'numero' => fake()->unique()->numberBetween(1, 100000),
                'numero_completo' => 'F-'.now()->year.'-'.fake()->unique()->numerify('####'),
                'total' => 100,
            ], $overrides));
    }

    private function filaDe(int $facturaId): array
    {
        $fila = ConsultaCobros::query(Tenant::current()?->getTenantKey() ?? auth()->user()?->tenant_id)
            ->where('facturas.id', $facturaId)
            ->first();

        $this->assertNotNull($fila, "No se encontró la fila de ConsultaCobros para la factura {$facturaId}");

        return (array) $fila;
    }

    public function test_factura_emitida_sin_cobros_coincide_con_el_modelo(): void
    {
        $tenant = Tenant::factory()->create();
        $cliente = Cliente::factory()->for($tenant, 'tenant')->create();
        $serie = Serie::factory()->for($tenant, 'tenant')->create();

        $factura = $this->crearFactura($tenant, $cliente, $serie, ['total' => 121]);

        $fila = ConsultaCobros::query($tenant->getTenantKey())->where('facturas.id', $factura->id)->first();

        $this->assertNotNull($fila);
        $this->assertEqualsWithDelta($factura->totalCobrable(), (float) $fila->total_cobrable, 0.001);
        $this->assertEqualsWithDelta($factura->montoCobrado(), (float) $fila->cobrado, 0.001);
        $this->assertEqualsWithDelta($factura->saldoPendiente(), (float) $fila->saldo_pendiente, 0.001);
        $this->assertSame($factura->estadoCobro()->value, $fila->estado_cobro);
    }

    public function test_factura_con_cobro_parcial_coincide_con_el_modelo(): void
    {
        $tenant = Tenant::factory()->create();
        $cliente = Cliente::factory()->for($tenant, 'tenant')->create();
        $serie = Serie::factory()->for($tenant, 'tenant')->create();

        $factura = $this->crearFactura($tenant, $cliente, $serie, ['total' => 200]);
        Pago::factory()->for($tenant, 'tenant')->create([
            'factura_id' => $factura->id,
            'importe' => 50,
            'anulado_at' => null,
        ]);

        $factura->refresh();
        $fila = ConsultaCobros::query($tenant->getTenantKey())->where('facturas.id', $factura->id)->first();

        $this->assertEqualsWithDelta($factura->totalCobrable(), (float) $fila->total_cobrable, 0.001);
        $this->assertEqualsWithDelta($factura->montoCobrado(), (float) $fila->cobrado, 0.001);
        $this->assertEqualsWithDelta($factura->saldoPendiente(), (float) $fila->saldo_pendiente, 0.001);
        $this->assertSame('parcial', $fila->estado_cobro);
        $this->assertSame($factura->estadoCobro()->value, $fila->estado_cobro);
    }

    public function test_factura_cobrada_del_todo_coincide_con_el_modelo(): void
    {
        $tenant = Tenant::factory()->create();
        $cliente = Cliente::factory()->for($tenant, 'tenant')->create();
        $serie = Serie::factory()->for($tenant, 'tenant')->create();

        $factura = $this->crearFactura($tenant, $cliente, $serie, ['total' => 100]);
        Pago::factory()->for($tenant, 'tenant')->create([
            'factura_id' => $factura->id,
            'importe' => 100,
            'anulado_at' => null,
        ]);
        // Un cobro anulado no debe sumar (paridad con montoCobrado()).
        Pago::factory()->for($tenant, 'tenant')->create([
            'factura_id' => $factura->id,
            'importe' => 30,
            'anulado_at' => now(),
        ]);

        $factura->refresh();
        $fila = ConsultaCobros::query($tenant->getTenantKey())->where('facturas.id', $factura->id)->first();

        $this->assertEqualsWithDelta($factura->montoCobrado(), (float) $fila->cobrado, 0.001);
        $this->assertSame('cobrada', $fila->estado_cobro);
        $this->assertSame($factura->estadoCobro()->value, $fila->estado_cobro);
        $this->assertEqualsWithDelta(0.0, (float) $fila->saldo_pendiente, 0.001);
    }

    public function test_factura_rectificada_por_sustitucion_usa_el_total_de_la_rectificativa(): void
    {
        $tenant = Tenant::factory()->create();
        $cliente = Cliente::factory()->for($tenant, 'tenant')->create();
        $serie = Serie::factory()->for($tenant, 'tenant')->create();

        $original = $this->crearFactura($tenant, $cliente, $serie, [
            'total' => 100,
            'estado' => EstadoFactura::Rectificada,
        ]);

        Factura::factory()->for($tenant, 'tenant')->create([
            'cliente_id' => $cliente->id,
            'serie_id' => $serie->id,
            'estado' => EstadoFactura::Emitida,
            'tipo' => TipoFactura::Rectificativa,
            'es_rectificativa' => true,
            'factura_rectificada_id' => $original->id,
            'tipo_rectificacion' => TipoRectificacion::Sustitucion,
            'numero' => fake()->unique()->numberBetween(1, 100000),
            'numero_completo' => 'F-'.now()->year.'-'.fake()->unique()->numerify('####'),
            'total' => 80,
        ]);

        $original->refresh();
        $fila = ConsultaCobros::query($tenant->getTenantKey())->where('facturas.id', $original->id)->first();

        $this->assertEqualsWithDelta($original->totalCobrable(), (float) $fila->total_cobrable, 0.001);
        $this->assertEqualsWithDelta(80.0, (float) $fila->total_cobrable, 0.001);
        $this->assertSame($original->estadoCobro()->value, $fila->estado_cobro);
    }

    public function test_factura_rectificada_por_diferencias_suma_ambos_totales(): void
    {
        $tenant = Tenant::factory()->create();
        $cliente = Cliente::factory()->for($tenant, 'tenant')->create();
        $serie = Serie::factory()->for($tenant, 'tenant')->create();

        $original = $this->crearFactura($tenant, $cliente, $serie, [
            'total' => 100,
            'estado' => EstadoFactura::Rectificada,
        ]);

        Factura::factory()->for($tenant, 'tenant')->create([
            'cliente_id' => $cliente->id,
            'serie_id' => $serie->id,
            'estado' => EstadoFactura::Emitida,
            'tipo' => TipoFactura::Rectificativa,
            'es_rectificativa' => true,
            'factura_rectificada_id' => $original->id,
            'tipo_rectificacion' => TipoRectificacion::Diferencias,
            'numero' => fake()->unique()->numberBetween(1, 100000),
            'numero_completo' => 'F-'.now()->year.'-'.fake()->unique()->numerify('####'),
            'total' => -20,
        ]);

        $original->refresh();
        $fila = ConsultaCobros::query($tenant->getTenantKey())->where('facturas.id', $original->id)->first();

        $this->assertEqualsWithDelta($original->totalCobrable(), (float) $fila->total_cobrable, 0.001);
        $this->assertEqualsWithDelta(80.0, (float) $fila->total_cobrable, 0.001);
        $this->assertSame($original->estadoCobro()->value, $fila->estado_cobro);
    }

    public function test_factura_sin_fecha_vencimiento_nunca_esta_vencida(): void
    {
        $tenant = Tenant::factory()->create();
        $cliente = Cliente::factory()->for($tenant, 'tenant')->create();
        $serie = Serie::factory()->for($tenant, 'tenant')->create();

        $factura = $this->crearFactura($tenant, $cliente, $serie, [
            'total' => 100,
            'fecha_vencimiento' => null,
        ]);

        $fila = ConsultaCobros::query($tenant->getTenantKey())->where('facturas.id', $factura->id)->first();

        $this->assertFalse((bool) $fila->vencida);
        $this->assertNull($fila->dias_retraso);
    }

    public function test_factura_vencida_con_saldo_calcula_dias_de_retraso(): void
    {
        $tenant = Tenant::factory()->create();
        $cliente = Cliente::factory()->for($tenant, 'tenant')->create();
        $serie = Serie::factory()->for($tenant, 'tenant')->create();

        $factura = $this->crearFactura($tenant, $cliente, $serie, [
            'total' => 100,
            'fecha_vencimiento' => now()->subDays(10)->toDateString(),
        ]);

        $fila = ConsultaCobros::query($tenant->getTenantKey())->where('facturas.id', $factura->id)->first();

        $this->assertTrue((bool) $fila->vencida);
        $this->assertSame(10, (int) $fila->dias_retraso);
    }

    public function test_factura_vencida_pero_ya_cobrada_no_cuenta_como_vencida(): void
    {
        $tenant = Tenant::factory()->create();
        $cliente = Cliente::factory()->for($tenant, 'tenant')->create();
        $serie = Serie::factory()->for($tenant, 'tenant')->create();

        $factura = $this->crearFactura($tenant, $cliente, $serie, [
            'total' => 100,
            'fecha_vencimiento' => now()->subDays(5)->toDateString(),
        ]);
        Pago::factory()->for($tenant, 'tenant')->create([
            'factura_id' => $factura->id,
            'importe' => 100,
            'anulado_at' => null,
        ]);

        $fila = ConsultaCobros::query($tenant->getTenantKey())->where('facturas.id', $factura->id)->first();

        $this->assertFalse((bool) $fila->vencida);
        $this->assertNull($fila->dias_retraso);
    }

    public function test_borradores_y_rectificativas_no_admiten_cobros_y_quedan_fuera(): void
    {
        $tenant = Tenant::factory()->create();
        $cliente = Cliente::factory()->for($tenant, 'tenant')->create();
        $serie = Serie::factory()->for($tenant, 'tenant')->create();

        $borrador = $this->crearFactura($tenant, $cliente, $serie, ['estado' => EstadoFactura::Borrador]);
        $original = $this->crearFactura($tenant, $cliente, $serie, ['estado' => EstadoFactura::Rectificada]);
        $rectificativa = Factura::factory()->for($tenant, 'tenant')->create([
            'cliente_id' => $cliente->id,
            'serie_id' => $serie->id,
            'estado' => EstadoFactura::Emitida,
            'tipo' => TipoFactura::Rectificativa,
            'es_rectificativa' => true,
            'factura_rectificada_id' => $original->id,
            'tipo_rectificacion' => TipoRectificacion::Sustitucion,
            'numero' => fake()->unique()->numberBetween(1, 100000),
            'numero_completo' => 'F-'.now()->year.'-'.fake()->unique()->numerify('####'),
            'total' => 50,
        ]);

        $ids = ConsultaCobros::query($tenant->getTenantKey())->pluck('facturas.id')->all();

        $this->assertNotContains($borrador->id, $ids);
        $this->assertNotContains($rectificativa->id, $ids);
        $this->assertContains($original->id, $ids);
        $this->assertSame($borrador->admiteCobros(), false);
        $this->assertSame($rectificativa->admiteCobros(), false);
        $this->assertSame($original->admiteCobros(), true);
    }
}
