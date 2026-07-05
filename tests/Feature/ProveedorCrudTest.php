<?php

namespace Tests\Feature;

use App\Models\Proveedor;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProveedorCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_muestra_la_vista(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $this->loginAs($user);

        $response = $this->get('/proveedores');

        $response->assertOk();
        $response->assertViewIs('proveedores.index');
    }

    public function test_store_crea_proveedor_con_nif_y_domicilio(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $this->loginAs($user);

        $response = $this->post('/proveedores', [
            'nombre' => 'Proveedor Uno',
            'razon_social' => 'Proveedor Uno SL',
            'nif' => 'B12345674',
            'direccion' => 'Calle Falsa 123',
            'pais' => 'ES',
        ]);

        $response->assertRedirect(route('proveedores.index'));
        $this->assertDatabaseHas('proveedores', ['nombre' => 'Proveedor Uno', 'tenant_id' => $tenant->id]);
    }

    public function test_update_edita_proveedor(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $proveedor = Proveedor::factory()->for($tenant)->create(['nombre' => 'Nombre Viejo']);
        $this->loginAs($user);

        $response = $this->put("/proveedores/{$proveedor->id}", [
            'nombre' => 'Nombre Nuevo',
            'pais' => 'ES',
        ]);

        $response->assertRedirect(route('proveedores.index'));
        $this->assertDatabaseHas('proveedores', ['id' => $proveedor->id, 'nombre' => 'Nombre Nuevo']);
    }

    public function test_index_json_lista_proveedores(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        Proveedor::factory()->for($tenant)->create(['nombre' => 'Proveedor A', 'razon_social' => null]);
        $this->loginAs($user);

        $response = $this->getJson('/proveedores');

        $response->assertOk();
        $response->assertJsonFragment(['nombre' => 'Proveedor A']);
    }

    public function test_destroy_hace_baja_logica_sin_romper_compras(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $proveedor = Proveedor::factory()->for($tenant)->create();
        $compra = \App\Models\Compra::factory()->for($tenant)->for($proveedor)->create();
        $this->loginAs($user);

        $response = $this->delete("/proveedores/{$proveedor->id}");

        $response->assertRedirect(route('proveedores.index'));
        $this->assertSoftDeleted($proveedor);
        $this->assertDatabaseHas('compras', ['id' => $compra->id, 'proveedor_id' => $proveedor->id]);
    }

    public function test_aislamiento_entre_tenants(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        Proveedor::factory()->for($tenantA)->create(['nombre' => 'Proveedor de A', 'razon_social' => null]);
        Proveedor::factory()->for($tenantB)->create(['nombre' => 'Proveedor de B', 'razon_social' => null]);

        $userA = User::factory()->create(['tenant_id' => $tenantA->id, 'password' => bcrypt('secret123')]);
        $this->loginAs($userA);

        $response = $this->getJson('/proveedores');

        $response->assertOk();
        $response->assertJsonFragment(['nombre' => 'Proveedor de A']);
        $response->assertJsonMissing(['nombre' => 'Proveedor de B']);
    }

    public function test_no_se_puede_editar_proveedor_de_otro_tenant(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $userA = User::factory()->create(['tenant_id' => $tenantA->id, 'password' => bcrypt('secret123')]);
        $proveedorB = Proveedor::factory()->for($tenantB)->create();

        $this->loginAs($userA);

        $response = $this->put("/proveedores/{$proveedorB->id}", [
            'nombre' => 'Intento',
            'pais' => 'ES',
        ]);

        $response->assertNotFound();
    }
}
