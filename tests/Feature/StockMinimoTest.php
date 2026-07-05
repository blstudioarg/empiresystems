<?php

namespace Tests\Feature;

use App\Enums\TipoArticulo;
use App\Models\Articulo;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockMinimoTest extends TestCase
{
    use RefreshDatabase;

    public function test_articulo_en_el_umbral_aparece_en_alertas(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        Articulo::factory()->for($tenant)->create([
            'tipo' => TipoArticulo::Producto,
            'gestion_stock' => true,
            'nombre' => 'En umbral',
            'stock_actual' => 5,
            'stock_minimo' => 5,
        ]);
        $this->loginAs($user);

        $response = $this->getJson('/stock');

        $response->assertOk();
        $response->assertJsonFragment(['nombre' => 'En umbral']);
    }

    public function test_articulo_bajo_minimo_aparece_en_alertas(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        Articulo::factory()->for($tenant)->create([
            'tipo' => TipoArticulo::Producto,
            'gestion_stock' => true,
            'nombre' => 'Bajo minimo',
            'stock_actual' => 3,
            'stock_minimo' => 5,
        ]);
        $this->loginAs($user);

        $response = $this->getJson('/stock');

        $response->assertOk();
        $response->assertJsonFragment(['nombre' => 'Bajo minimo']);
    }

    public function test_articulo_por_encima_del_minimo_no_aparece(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        Articulo::factory()->for($tenant)->create([
            'tipo' => TipoArticulo::Producto,
            'gestion_stock' => true,
            'nombre' => 'Sobre minimo',
            'stock_actual' => 50,
            'stock_minimo' => 5,
        ]);
        $this->loginAs($user);

        $response = $this->getJson('/stock');

        $response->assertOk();
        $response->assertJson(['alertas' => []]);
    }

    public function test_articulo_sin_minimo_definido_no_aparece(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        Articulo::factory()->for($tenant)->create([
            'tipo' => TipoArticulo::Producto,
            'gestion_stock' => true,
            'nombre' => 'Sin minimo',
            'stock_actual' => 1,
            'stock_minimo' => null,
        ]);
        $this->loginAs($user);

        $response = $this->getJson('/stock');

        $response->assertOk();
        $response->assertJson(['alertas' => []]);
    }

    public function test_aislamiento_entre_tenants_en_alertas(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $userA = User::factory()->create(['tenant_id' => $tenantA->id, 'password' => bcrypt('secret123')]);

        Articulo::factory()->for($tenantA)->create([
            'tipo' => TipoArticulo::Producto,
            'gestion_stock' => true,
            'nombre' => 'De tenant A',
            'stock_actual' => 1,
            'stock_minimo' => 5,
        ]);
        Articulo::factory()->for($tenantB)->create([
            'tipo' => TipoArticulo::Producto,
            'gestion_stock' => true,
            'nombre' => 'De tenant B',
            'stock_actual' => 1,
            'stock_minimo' => 5,
        ]);

        $this->loginAs($userA);

        $response = $this->getJson('/stock');

        $response->assertOk();
        $response->assertJsonFragment(['nombre' => 'De tenant A']);
        $response->assertJsonMissing(['nombre' => 'De tenant B']);
    }
}
