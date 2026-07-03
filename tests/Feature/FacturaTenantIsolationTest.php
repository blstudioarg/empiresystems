<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Factura;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FacturaTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private function payloadValido(Cliente $cliente): array
    {
        return [
            'cliente_id' => $cliente->id,
            'fecha_expedicion' => now()->toDateString(),
            'forma_pago' => 'transferencia',
            'lineas' => [
                ['concepto' => 'Servicio', 'cantidad' => 1, 'precio_unitario' => 100, 'tipo_impositivo' => 21],
            ],
        ];
    }

    public function test_el_listado_de_un_tenant_no_incluye_facturas_de_otro(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $userA = User::factory()->create(['tenant_id' => $tenantA->id, 'password' => bcrypt('secret123')]);

        $clienteA = Cliente::factory()->create(['tenant_id' => $tenantA->id]);
        $clienteB = Cliente::factory()->create(['tenant_id' => $tenantB->id]);

        Factura::factory()->create(['tenant_id' => $tenantA->id, 'cliente_id' => $clienteA->id]);
        Factura::factory()->create(['tenant_id' => $tenantB->id, 'cliente_id' => $clienteB->id]);

        $this->loginAs($userA);

        $response = $this->getJson('/facturas');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }

    public function test_no_se_puede_crear_factura_con_cliente_de_otro_tenant(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $userA = User::factory()->create(['tenant_id' => $tenantA->id, 'password' => bcrypt('secret123')]);
        $clienteB = Cliente::factory()->create(['tenant_id' => $tenantB->id]);

        $this->loginAs($userA);

        $response = $this->post('/facturas', $this->payloadValido($clienteB));

        $response->assertSessionHasErrors('cliente_id');
        $this->assertDatabaseCount('facturas', 0);
    }

    public function test_no_se_puede_editar_una_factura_de_otro_tenant(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $userA = User::factory()->create(['tenant_id' => $tenantA->id, 'password' => bcrypt('secret123')]);
        $clienteA = Cliente::factory()->create(['tenant_id' => $tenantA->id]);
        $clienteB = Cliente::factory()->create(['tenant_id' => $tenantB->id]);
        $facturaB = Factura::factory()->create(['tenant_id' => $tenantB->id, 'cliente_id' => $clienteB->id]);

        $this->loginAs($userA);

        // El payload usa un cliente válido para el tenant A (la validación pasa); lo que se
        // comprueba es que la factura de B no es resoluble bajo el TenantScope de A.
        $response = $this->put("/facturas/{$facturaB->id}", $this->payloadValido($clienteA));

        $response->assertNotFound();
    }

    public function test_no_se_puede_ver_edicion_de_una_factura_de_otro_tenant(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $userA = User::factory()->create(['tenant_id' => $tenantA->id, 'password' => bcrypt('secret123')]);
        $clienteB = Cliente::factory()->create(['tenant_id' => $tenantB->id]);
        $facturaB = Factura::factory()->create(['tenant_id' => $tenantB->id, 'cliente_id' => $clienteB->id]);

        $this->loginAs($userA);

        $response = $this->get("/facturas/{$facturaB->id}/editar");

        $response->assertNotFound();
    }

    public function test_no_se_puede_eliminar_una_factura_de_otro_tenant(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $userA = User::factory()->create(['tenant_id' => $tenantA->id, 'password' => bcrypt('secret123')]);
        $clienteB = Cliente::factory()->create(['tenant_id' => $tenantB->id]);
        $facturaB = Factura::factory()->create(['tenant_id' => $tenantB->id, 'cliente_id' => $clienteB->id]);

        $this->loginAs($userA);

        $response = $this->delete("/facturas/{$facturaB->id}");

        $response->assertNotFound();
        $this->assertNotSoftDeleted($facturaB);
    }
}
