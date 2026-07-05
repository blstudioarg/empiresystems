<?php

namespace Tests\Feature;

use App\Enums\EstadoCobro;
use App\Models\Cliente;
use App\Models\Factura;
use App\Models\Pago;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PagoAnulacionTest extends TestCase
{
    use RefreshDatabase;

    public function test_anular_un_pago_vigente_recalcula_saldo_y_estado(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $cliente = Cliente::factory()->create(['tenant_id' => $tenant->id]);
        $factura = Factura::factory()->emitida()->create([
            'tenant_id' => $tenant->id,
            'cliente_id' => $cliente->id,
            'total' => 100.00,
        ]);
        $pago = Pago::factory()->create(['tenant_id' => $tenant->id, 'factura_id' => $factura->id, 'importe' => 40.00]);

        $this->loginAs($user);

        $response = $this->postJson("/pagos/{$pago->id}/anular");

        $response->assertOk();

        $pago->refresh();
        $factura->refresh();
        $this->assertNotNull($pago->anulado_at);
        $this->assertTrue($pago->estaAnulado());
        $this->assertSame(100.0, $factura->saldoPendiente());
        $this->assertSame(EstadoCobro::Pendiente, $factura->estadoCobro());
    }

    public function test_no_se_puede_anular_un_pago_ya_anulado(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $cliente = Cliente::factory()->create(['tenant_id' => $tenant->id]);
        $factura = Factura::factory()->emitida()->create([
            'tenant_id' => $tenant->id,
            'cliente_id' => $cliente->id,
            'total' => 100.00,
        ]);
        $pago = Pago::factory()->anulado()->create(['tenant_id' => $tenant->id, 'factura_id' => $factura->id, 'importe' => 40.00]);

        $this->loginAs($user);

        $response = $this->postJson("/pagos/{$pago->id}/anular");

        $response->assertStatus(422);
    }
}
