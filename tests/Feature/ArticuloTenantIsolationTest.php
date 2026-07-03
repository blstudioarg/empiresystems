<?php

namespace Tests\Feature;

use App\Models\Articulo;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArticuloTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_listado_de_un_tenant_no_incluye_articulos_de_otro(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $userA = User::factory()->create([
            'tenant_id' => $tenantA->id,
            'password' => bcrypt('secret123'),
        ]);

        Articulo::factory()->create(['tenant_id' => $tenantA->id, 'nombre' => 'Artículo de A']);
        Articulo::factory()->create(['tenant_id' => $tenantB->id, 'nombre' => 'Artículo de B']);

        $this->loginAs($userA);

        $response = $this->getJson('/articulos');

        $response->assertOk();
        $response->assertJsonFragment(['nombre' => 'Artículo de A']);
        $response->assertJsonMissing(['nombre' => 'Artículo de B']);
    }

    public function test_crear_articulo_asigna_el_tenant_activo(): void
    {
        $tenantA = Tenant::factory()->create();
        $userA = User::factory()->create([
            'tenant_id' => $tenantA->id,
            'password' => bcrypt('secret123'),
        ]);

        $this->loginAs($userA);

        $this->post('/articulos', [
            'tipo' => 'producto',
            'nombre' => 'Nuevo Artículo',
            'precio' => 10,
            'tipo_impositivo' => 21,
        ]);

        $articulo = Articulo::where('nombre', 'Nuevo Artículo')->first();

        $this->assertNotNull($articulo);
        $this->assertEquals($tenantA->id, $articulo->tenant_id);
    }

    public function test_no_se_puede_editar_un_articulo_de_otro_tenant(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $userA = User::factory()->create([
            'tenant_id' => $tenantA->id,
            'password' => bcrypt('secret123'),
        ]);

        $articuloB = Articulo::factory()->create(['tenant_id' => $tenantB->id]);

        $this->loginAs($userA);

        $response = $this->put("/articulos/{$articuloB->id}", [
            'tipo' => 'producto',
            'nombre' => 'Intento de edición',
            'precio' => 10,
            'tipo_impositivo' => 21,
        ]);

        $response->assertNotFound();
    }

    public function test_no_se_puede_eliminar_un_articulo_de_otro_tenant(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $userA = User::factory()->create([
            'tenant_id' => $tenantA->id,
            'password' => bcrypt('secret123'),
        ]);

        $articuloB = Articulo::factory()->create(['tenant_id' => $tenantB->id]);

        $this->loginAs($userA);

        $response = $this->delete("/articulos/{$articuloB->id}");

        $response->assertNotFound();

        $this->assertNotSoftDeleted($articuloB);
    }
}
