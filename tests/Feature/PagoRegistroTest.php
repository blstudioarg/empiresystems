<?php

namespace Tests\Feature;

use App\Enums\EstadoCobro;
use App\Enums\EstadoFactura;
use App\Models\Cliente;
use App\Models\Factura;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PagoRegistroTest extends TestCase
{
    use RefreshDatabase;

    private function crearFacturaEmitida(Tenant $tenant, float $total = 121.00): Factura
    {
        $cliente = Cliente::factory()->create(['tenant_id' => $tenant->id]);

        return Factura::factory()->emitida()->create([
            'tenant_id' => $tenant->id,
            'cliente_id' => $cliente->id,
            'total' => $total,
        ]);
    }

    public function test_registrar_pago_total_salda_la_factura(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $factura = $this->crearFacturaEmitida($tenant, 121.00);

        $this->loginAs($user);

        $response = $this->postJson("/facturas/{$factura->id}/pagos", [
            'fecha' => now()->toDateString(),
            'importe' => 121.00,
            'metodo' => 'transferencia',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('pagos', ['factura_id' => $factura->id, 'importe' => 121.00]);

        $factura->refresh();
        $this->assertSame(0.0, $factura->saldoPendiente());
        $this->assertSame(EstadoCobro::Cobrada, $factura->estadoCobro());
    }

    public function test_registrar_pago_parcial_reduce_el_saldo(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $factura = $this->crearFacturaEmitida($tenant, 121.00);

        $this->loginAs($user);

        $response = $this->postJson("/facturas/{$factura->id}/pagos", [
            'fecha' => now()->toDateString(),
            'importe' => 50.00,
            'metodo' => 'efectivo',
        ]);

        $response->assertCreated();

        $factura->refresh();
        $this->assertSame(71.0, $factura->saldoPendiente());
        $this->assertSame(EstadoCobro::Parcial, $factura->estadoCobro());
    }

    public function test_no_se_puede_registrar_pago_contra_factura_en_borrador(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $cliente = Cliente::factory()->create(['tenant_id' => $tenant->id]);
        $factura = Factura::factory()->create([
            'tenant_id' => $tenant->id,
            'cliente_id' => $cliente->id,
            'estado' => EstadoFactura::Borrador,
        ]);

        $this->loginAs($user);

        $response = $this->postJson("/facturas/{$factura->id}/pagos", [
            'fecha' => now()->toDateString(),
            'importe' => 10.00,
            'metodo' => 'efectivo',
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseCount('pagos', 0);
    }

    public function test_no_se_puede_registrar_pago_que_excede_el_saldo(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $factura = $this->crearFacturaEmitida($tenant, 100.00);

        $this->loginAs($user);

        $response = $this->postJson("/facturas/{$factura->id}/pagos", [
            'fecha' => now()->toDateString(),
            'importe' => 150.00,
            'metodo' => 'efectivo',
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseCount('pagos', 0);
    }

    public function test_no_se_puede_registrar_pago_con_importe_cero_o_negativo(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $factura = $this->crearFacturaEmitida($tenant, 100.00);

        $this->loginAs($user);

        $response = $this->postJson("/facturas/{$factura->id}/pagos", [
            'fecha' => now()->toDateString(),
            'importe' => 0,
            'metodo' => 'efectivo',
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseCount('pagos', 0);

        $response = $this->postJson("/facturas/{$factura->id}/pagos", [
            'fecha' => now()->toDateString(),
            'importe' => -10,
            'metodo' => 'efectivo',
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseCount('pagos', 0);
    }
}
