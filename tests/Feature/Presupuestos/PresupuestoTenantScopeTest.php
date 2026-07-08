<?php

namespace Tests\Feature\Presupuestos;

use App\Models\Cliente;
use App\Models\Presupuesto;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PresupuestoTenantScopeTest extends TestCase
{
    use RefreshDatabase;

    private function payloadValido(Cliente $cliente): array
    {
        return [
            'cliente_id' => $cliente->id,
            'fecha_emision' => now()->toDateString(),
            'lineas' => [
                ['concepto' => 'Servicio', 'cantidad' => 1, 'precio_unitario' => 100, 'tipo_impositivo' => 21],
            ],
        ];
    }

    public function test_el_listado_de_un_tenant_no_incluye_presupuestos_de_otro(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $userA = User::factory()->create(['tenant_id' => $tenantA->id, 'password' => bcrypt('secret123')]);

        $clienteA = Cliente::factory()->create(['tenant_id' => $tenantA->id]);
        $clienteB = Cliente::factory()->create(['tenant_id' => $tenantB->id]);

        Presupuesto::factory()->create(['tenant_id' => $tenantA->id, 'cliente_id' => $clienteA->id]);
        Presupuesto::factory()->create(['tenant_id' => $tenantB->id, 'cliente_id' => $clienteB->id]);

        $this->loginAs($userA);

        $response = $this->getJson('/presupuestos');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }

    public function test_no_se_puede_crear_presupuesto_con_cliente_de_otro_tenant(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $userA = User::factory()->create(['tenant_id' => $tenantA->id, 'password' => bcrypt('secret123')]);
        $clienteB = Cliente::factory()->create(['tenant_id' => $tenantB->id]);

        $this->loginAs($userA);

        $response = $this->post('/presupuestos', $this->payloadValido($clienteB));

        $response->assertSessionHasErrors('cliente_id');
        $this->assertDatabaseCount('presupuestos', 0);
    }

    public function test_no_se_puede_ver_el_pdf_de_un_presupuesto_de_otro_tenant(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $userA = User::factory()->create(['tenant_id' => $tenantA->id, 'password' => bcrypt('secret123')]);
        $clienteB = Cliente::factory()->create(['tenant_id' => $tenantB->id]);
        $presupuestoB = Presupuesto::factory()->create(['tenant_id' => $tenantB->id, 'cliente_id' => $clienteB->id]);

        $this->loginAs($userA);

        $response = $this->get("/presupuestos/{$presupuestoB->id}/pdf");

        $response->assertNotFound();
    }

    public function test_no_se_puede_editar_un_presupuesto_de_otro_tenant(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $userA = User::factory()->create(['tenant_id' => $tenantA->id, 'password' => bcrypt('secret123')]);
        $clienteB = Cliente::factory()->create(['tenant_id' => $tenantB->id]);
        $presupuestoB = Presupuesto::factory()->create(['tenant_id' => $tenantB->id, 'cliente_id' => $clienteB->id]);

        $this->loginAs($userA);

        $response = $this->get("/presupuestos/{$presupuestoB->id}/editar");

        $response->assertNotFound();
    }

    public function test_no_se_puede_eliminar_un_presupuesto_de_otro_tenant(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $userA = User::factory()->create(['tenant_id' => $tenantA->id, 'password' => bcrypt('secret123')]);
        $clienteB = Cliente::factory()->create(['tenant_id' => $tenantB->id]);
        $presupuestoB = Presupuesto::factory()->create(['tenant_id' => $tenantB->id, 'cliente_id' => $clienteB->id]);

        $this->loginAs($userA);

        $response = $this->delete("/presupuestos/{$presupuestoB->id}");

        $response->assertNotFound();
        $this->assertNotSoftDeleted($presupuestoB);
    }
}
