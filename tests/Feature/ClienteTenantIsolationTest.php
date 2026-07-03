<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClienteTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_listado_de_un_tenant_no_incluye_clientes_de_otro(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $userA = User::factory()->create([
            'tenant_id' => $tenantA->id,
            'password' => bcrypt('secret123'),
        ]);

        $clienteA = Cliente::factory()->create(['tenant_id' => $tenantA->id, 'nombre' => 'Cliente de A']);
        $clienteB = Cliente::factory()->create(['tenant_id' => $tenantB->id, 'nombre' => 'Cliente de B']);

        $this->loginAs($userA);

        $response = $this->getJson('/clientes');

        $response->assertOk();
        $response->assertJsonFragment(['nombre' => 'Cliente de A']);
        $response->assertJsonMissing(['nombre' => 'Cliente de B']);
    }

    public function test_crear_cliente_asigna_el_tenant_activo(): void
    {
        $tenantA = Tenant::factory()->create();
        $userA = User::factory()->create([
            'tenant_id' => $tenantA->id,
            'password' => bcrypt('secret123'),
        ]);

        $this->loginAs($userA);

        $this->post('/clientes', [
            'tipo' => 'particular',
            'nombre' => 'Nuevo Cliente',
            'pais' => 'ES',
        ]);

        $cliente = Cliente::where('nombre', 'Nuevo Cliente')->first();

        $this->assertNotNull($cliente);
        $this->assertEquals($tenantA->id, $cliente->tenant_id);
    }

    public function test_no_se_puede_editar_un_cliente_de_otro_tenant(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $userA = User::factory()->create([
            'tenant_id' => $tenantA->id,
            'password' => bcrypt('secret123'),
        ]);

        $clienteB = Cliente::factory()->create(['tenant_id' => $tenantB->id]);

        $this->loginAs($userA);

        $response = $this->put("/clientes/{$clienteB->id}", [
            'tipo' => 'particular',
            'nombre' => 'Intento de edición',
            'pais' => 'ES',
        ]);

        $response->assertNotFound();
    }

    public function test_no_se_puede_eliminar_un_cliente_de_otro_tenant(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $userA = User::factory()->create([
            'tenant_id' => $tenantA->id,
            'password' => bcrypt('secret123'),
        ]);

        $clienteB = Cliente::factory()->create(['tenant_id' => $tenantB->id]);

        $this->loginAs($userA);

        $response = $this->delete("/clientes/{$clienteB->id}");

        $response->assertNotFound();

        $this->assertNotSoftDeleted($clienteB);
    }
}
