<?php

namespace Tests\Feature;

use App\Enums\EstadoCompra;
use App\Enums\TipoArticulo;
use App\Models\Articulo;
use App\Models\Compra;
use App\Models\Proveedor;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompraStockTest extends TestCase
{
    use RefreshDatabase;

    public function test_confirmar_compra_suma_stock_por_linea_con_articulo_gestionado(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $proveedor = Proveedor::factory()->for($tenant)->create();
        $articulo = Articulo::factory()->for($tenant)->create([
            'tipo' => TipoArticulo::Producto,
            'gestion_stock' => true,
            'stock_actual' => 5,
        ]);

        $compra = Compra::factory()->for($tenant)->for($proveedor)->create();
        $compra->lineas()->create([
            'tenant_id' => $tenant->id,
            'articulo_id' => $articulo->id,
            'concepto' => $articulo->nombre,
            'cantidad' => 20,
            'precio_unitario' => 10,
            'base' => 200,
            'tipo_impositivo' => 21,
            'cuota_impuesto' => 42,
            'orden' => 0,
        ]);

        $this->loginAs($user);

        $response = $this->post("/compras/{$compra->id}/confirmar");

        $response->assertRedirect(route('compras.show', $compra));
        $this->assertEquals(25, (float) $articulo->refresh()->stock_actual);
        $this->assertDatabaseHas('movimientos_stock', ['articulo_id' => $articulo->id, 'compra_id' => $compra->id, 'tipo' => 'entrada']);
        $this->assertDatabaseHas('compras', ['id' => $compra->id, 'estado' => EstadoCompra::Confirmada->value]);
    }

    public function test_anular_compra_confirmada_revierte_stock(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $proveedor = Proveedor::factory()->for($tenant)->create();
        $articulo = Articulo::factory()->for($tenant)->create([
            'tipo' => TipoArticulo::Producto,
            'gestion_stock' => true,
            'stock_actual' => 5,
        ]);

        $compra = Compra::factory()->for($tenant)->for($proveedor)->create();
        $compra->lineas()->create([
            'tenant_id' => $tenant->id,
            'articulo_id' => $articulo->id,
            'concepto' => $articulo->nombre,
            'cantidad' => 20,
            'precio_unitario' => 10,
            'base' => 200,
            'tipo_impositivo' => 21,
            'cuota_impuesto' => 42,
            'orden' => 0,
        ]);

        $this->loginAs($user);
        $this->post("/compras/{$compra->id}/confirmar");
        $this->assertEquals(25, (float) $articulo->refresh()->stock_actual);

        $response = $this->post("/compras/{$compra->id}/anular");

        $response->assertRedirect(route('compras.show', $compra));
        $this->assertEquals(5, (float) $articulo->refresh()->stock_actual);
        $this->assertDatabaseHas('compras', ['id' => $compra->id, 'estado' => EstadoCompra::Anulada->value]);
    }

    public function test_linea_libre_o_sin_gestion_no_mueve_stock(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $proveedor = Proveedor::factory()->for($tenant)->create();
        $articuloSinGestion = Articulo::factory()->for($tenant)->create([
            'tipo' => TipoArticulo::Producto,
            'gestion_stock' => false,
            'stock_actual' => null,
        ]);

        $compra = Compra::factory()->for($tenant)->for($proveedor)->create();
        $compra->lineas()->create([
            'tenant_id' => $tenant->id,
            'articulo_id' => null,
            'concepto' => 'Gasto libre',
            'cantidad' => 1,
            'precio_unitario' => 100,
            'base' => 100,
            'tipo_impositivo' => 21,
            'cuota_impuesto' => 21,
            'orden' => 0,
        ]);
        $compra->lineas()->create([
            'tenant_id' => $tenant->id,
            'articulo_id' => $articuloSinGestion->id,
            'concepto' => $articuloSinGestion->nombre,
            'cantidad' => 3,
            'precio_unitario' => 10,
            'base' => 30,
            'tipo_impositivo' => 21,
            'cuota_impuesto' => 6.3,
            'orden' => 1,
        ]);

        $this->loginAs($user);

        $response = $this->post("/compras/{$compra->id}/confirmar");

        $response->assertRedirect(route('compras.show', $compra));
        $this->assertDatabaseCount('movimientos_stock', 0);
        $this->assertNull($articuloSinGestion->refresh()->stock_actual);
    }

    public function test_compra_confirmada_es_inmutable(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $proveedor = Proveedor::factory()->for($tenant)->create();
        $compra = Compra::factory()->for($tenant)->for($proveedor)->confirmada()->create();
        $this->loginAs($user);

        $response = $this->put("/compras/{$compra->id}", [
            'proveedor_id' => $proveedor->id,
            'fecha' => now()->toDateString(),
            'lineas' => [['concepto' => 'x', 'cantidad' => 1, 'precio_unitario' => 1, 'tipo_impositivo' => 21]],
        ]);

        $response->assertForbidden();
    }

    public function test_totales_se_calculan_en_servidor(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $proveedor = Proveedor::factory()->for($tenant)->create();
        $this->loginAs($user);

        $response = $this->post('/compras', [
            'proveedor_id' => $proveedor->id,
            'fecha' => now()->toDateString(),
            'lineas' => [
                ['concepto' => 'Material', 'cantidad' => 2, 'precio_unitario' => 50, 'tipo_impositivo' => 21],
            ],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('compras', [
            'proveedor_id' => $proveedor->id,
            'base_total' => 100,
            'cuota_impuesto_total' => 21,
            'total' => 121,
        ]);
    }

    public function test_aislamiento_entre_tenants_en_compras(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $proveedorA = Proveedor::factory()->for($tenantA)->create();
        $proveedorB = Proveedor::factory()->for($tenantB)->create();
        Compra::factory()->for($tenantA)->for($proveedorA)->create(['numero_documento' => 'COMPRA-A']);
        Compra::factory()->for($tenantB)->for($proveedorB)->create(['numero_documento' => 'COMPRA-B']);

        $userA = User::factory()->create(['tenant_id' => $tenantA->id, 'password' => bcrypt('secret123')]);
        $this->loginAs($userA);

        $response = $this->getJson('/compras');

        $response->assertOk();
        $response->assertJsonFragment(['numero_documento' => 'COMPRA-A']);
        $response->assertJsonMissing(['numero_documento' => 'COMPRA-B']);
    }
}
