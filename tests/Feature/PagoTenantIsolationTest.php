<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Factura;
use App\Models\Pago;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PagoTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_se_puede_registrar_pago_contra_factura_de_otro_tenant(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $userA = User::factory()->create(['tenant_id' => $tenantA->id, 'password' => bcrypt('secret123')]);
        $clienteB = Cliente::factory()->create(['tenant_id' => $tenantB->id]);
        $facturaB = Factura::factory()->emitida()->create([
            'tenant_id' => $tenantB->id,
            'cliente_id' => $clienteB->id,
            'total' => 100.00,
        ]);

        $this->loginAs($userA);

        $response = $this->postJson("/facturas/{$facturaB->id}/pagos", [
            'fecha' => now()->toDateString(),
            'importe' => 10.00,
            'metodo' => 'efectivo',
        ]);

        $response->assertNotFound();
        $this->assertDatabaseCount('pagos', 0);
    }

    public function test_no_se_puede_anular_un_pago_de_otro_tenant(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $userA = User::factory()->create(['tenant_id' => $tenantA->id, 'password' => bcrypt('secret123')]);
        $clienteB = Cliente::factory()->create(['tenant_id' => $tenantB->id]);
        $facturaB = Factura::factory()->emitida()->create([
            'tenant_id' => $tenantB->id,
            'cliente_id' => $clienteB->id,
            'total' => 100.00,
        ]);
        $pagoB = Pago::factory()->create(['tenant_id' => $tenantB->id, 'factura_id' => $facturaB->id, 'importe' => 40.00]);

        $this->loginAs($userA);

        $response = $this->postJson("/pagos/{$pagoB->id}/anular");

        $response->assertNotFound();

        $pagoB->refresh();
        $this->assertNull($pagoB->anulado_at);
    }

    public function test_registrar_y_anular_en_tenant_a_no_altera_saldos_de_tenant_b(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $userA = User::factory()->create(['tenant_id' => $tenantA->id, 'password' => bcrypt('secret123')]);
        $clienteA = Cliente::factory()->create(['tenant_id' => $tenantA->id]);
        $facturaA = Factura::factory()->emitida()->create([
            'tenant_id' => $tenantA->id,
            'cliente_id' => $clienteA->id,
            'total' => 100.00,
        ]);

        $clienteB = Cliente::factory()->create(['tenant_id' => $tenantB->id]);
        $facturaB = Factura::factory()->emitida()->create([
            'tenant_id' => $tenantB->id,
            'cliente_id' => $clienteB->id,
            'total' => 100.00,
        ]);
        $pagoB = Pago::factory()->create(['tenant_id' => $tenantB->id, 'factura_id' => $facturaB->id, 'importe' => 40.00]);

        $this->loginAs($userA);

        $this->postJson("/facturas/{$facturaA->id}/pagos", [
            'fecha' => now()->toDateString(),
            'importe' => 30.00,
            'metodo' => 'efectivo',
        ])->assertCreated();

        $this->assertDatabaseHas('pagos', ['id' => $pagoB->id, 'anulado_at' => null, 'importe' => 40.00]);
        $this->assertSame(1, \DB::table('pagos')->where('tenant_id', $tenantB->id)->count());
    }
}
