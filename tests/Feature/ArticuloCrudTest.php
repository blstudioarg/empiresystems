<?php

namespace Tests\Feature;

use App\Models\Articulo;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArticuloCrudTest extends TestCase
{
    use RefreshDatabase;

    // --- Régimen fiscal (FR-008/FR-009, Principio II) ---

    public function test_tenant_iva_rechaza_tipo_impositivo_de_igic(): void
    {
        $tenant = Tenant::factory()->create(['regimen_impositivo' => 'iva']);
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $this->loginAs($user);

        $response = $this->post('/articulos', [
            'tipo' => 'producto',
            'nombre' => 'Artículo IVA',
            'precio' => 10,
            'tipo_impositivo' => 7,
        ]);

        $response->assertSessionHasErrors('tipo_impositivo');
        $this->assertDatabaseCount('articulos', 0);
    }

    public function test_tenant_iva_acepta_tipo_impositivo_21(): void
    {
        $tenant = Tenant::factory()->create(['regimen_impositivo' => 'iva']);
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $this->loginAs($user);

        $response = $this->post('/articulos', [
            'tipo' => 'producto',
            'nombre' => 'Artículo IVA',
            'precio' => 10,
            'tipo_impositivo' => 21,
        ]);

        $response->assertRedirect(route('articulos.index'));
        $this->assertDatabaseHas('articulos', ['nombre' => 'Artículo IVA', 'tipo_impositivo' => 21]);
    }

    public function test_tenant_igic_rechaza_tipo_impositivo_21(): void
    {
        $tenant = Tenant::factory()->create(['regimen_impositivo' => 'igic']);
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $this->loginAs($user);

        $response = $this->post('/articulos', [
            'tipo' => 'producto',
            'nombre' => 'Artículo IGIC',
            'precio' => 10,
            'tipo_impositivo' => 21,
        ]);

        $response->assertSessionHasErrors('tipo_impositivo');
        $this->assertDatabaseCount('articulos', 0);
    }

    public function test_tenant_igic_acepta_tipo_impositivo_7(): void
    {
        $tenant = Tenant::factory()->create(['regimen_impositivo' => 'igic']);
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $this->loginAs($user);

        $response = $this->post('/articulos', [
            'tipo' => 'producto',
            'nombre' => 'Artículo IGIC',
            'precio' => 10,
            'tipo_impositivo' => 7,
        ]);

        $response->assertRedirect(route('articulos.index'));
        $this->assertDatabaseHas('articulos', ['nombre' => 'Artículo IGIC', 'tipo_impositivo' => 7]);
    }

    public function test_tenant_ipsi_acepta_cualquier_valor_entre_0_y_100(): void
    {
        $tenant = Tenant::factory()->create(['regimen_impositivo' => 'ipsi']);
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $this->loginAs($user);

        $response = $this->post('/articulos', [
            'tipo' => 'producto',
            'nombre' => 'Artículo IPSI',
            'precio' => 10,
            'tipo_impositivo' => 12.5,
        ]);

        $response->assertRedirect(route('articulos.index'));
        $this->assertDatabaseHas('articulos', ['nombre' => 'Artículo IPSI', 'tipo_impositivo' => 12.5]);
    }

    // --- Index (US1) ---

    public function test_index_muestra_la_vista_sin_articulos_embebidos(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);

        Articulo::factory()->for($tenant)->producto()->create(['nombre' => 'Producto Uno']);

        $this->loginAs($user);

        $response = $this->get('/articulos');

        $response->assertOk();
        $response->assertViewIs('articulos.index');
    }

    public function test_index_json_devuelve_solo_articulos_del_tenant_activo_con_metricas_correctas(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);

        Articulo::factory()->for($tenant)->producto()->create(['nombre' => 'Producto Uno']);
        Articulo::factory()->for($tenant)->producto()->create(['nombre' => 'Producto Dos']);
        Articulo::factory()->for($tenant)->servicio()->create(['nombre' => 'Servicio Uno']);

        $this->loginAs($user);

        $response = $this->getJson('/articulos');

        $response->assertOk();
        $response->assertJsonCount(3, 'data');
        $response->assertJsonFragment(['nombre' => 'Producto Uno']);
        $response->assertJsonFragment(['nombre' => 'Producto Dos']);
        $response->assertJsonFragment(['nombre' => 'Servicio Uno']);
        $response->assertJson([
            'totales' => ['total' => 3, 'productos' => 2, 'servicios' => 1],
        ]);
    }

    // --- Store (US2) ---

    public function test_store_crea_producto_valido(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $this->loginAs($user);

        $response = $this->post('/articulos', [
            'tipo' => 'producto',
            'nombre' => 'Producto Nuevo',
            'precio' => 25.5,
            'tipo_impositivo' => 21,
        ]);

        $response->assertRedirect(route('articulos.index'));
        $this->assertDatabaseHas('articulos', ['nombre' => 'Producto Nuevo', 'tenant_id' => $tenant->id]);
    }

    public function test_store_crea_servicio_valido(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $this->loginAs($user);

        $response = $this->post('/articulos', [
            'tipo' => 'servicio',
            'nombre' => 'Servicio Nuevo',
            'precio' => 50,
            'tipo_impositivo' => 21,
        ]);

        $response->assertRedirect(route('articulos.index'));
        $this->assertDatabaseHas('articulos', ['nombre' => 'Servicio Nuevo', 'tenant_id' => $tenant->id]);
    }

    public function test_store_falla_sin_nombre(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $this->loginAs($user);

        $response = $this->post('/articulos', [
            'tipo' => 'producto',
            'precio' => 10,
            'tipo_impositivo' => 21,
        ]);

        $response->assertSessionHasErrors('nombre');
        $this->assertDatabaseCount('articulos', 0);
    }

    public function test_store_falla_con_precio_negativo(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $this->loginAs($user);

        $response = $this->post('/articulos', [
            'tipo' => 'producto',
            'nombre' => 'Producto Negativo',
            'precio' => -5,
            'tipo_impositivo' => 21,
        ]);

        $response->assertSessionHasErrors('precio');
        $this->assertDatabaseCount('articulos', 0);
    }

    public function test_store_producto_con_gestion_stock_exige_stock_actual(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $this->loginAs($user);

        $response = $this->post('/articulos', [
            'tipo' => 'producto',
            'nombre' => 'Producto Con Stock',
            'precio' => 10,
            'tipo_impositivo' => 21,
            'gestion_stock' => true,
        ]);

        $response->assertSessionHasErrors('stock_actual');
        $this->assertDatabaseCount('articulos', 0);
    }

    public function test_store_servicio_ignora_campos_de_stock_aunque_se_envien(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $this->loginAs($user);

        $response = $this->post('/articulos', [
            'tipo' => 'servicio',
            'nombre' => 'Servicio Con Stock Enviado',
            'precio' => 10,
            'tipo_impositivo' => 21,
            'gestion_stock' => true,
            'stock_actual' => 5,
        ]);

        $response->assertRedirect(route('articulos.index'));
        $this->assertDatabaseHas('articulos', [
            'nombre' => 'Servicio Con Stock Enviado',
            'gestion_stock' => false,
            'stock_actual' => null,
        ]);
    }

    public function test_metricas_se_actualizan_tras_alta(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $this->loginAs($user);

        $this->post('/articulos', [
            'tipo' => 'producto',
            'nombre' => 'Nuevo Artículo',
            'precio' => 10,
            'tipo_impositivo' => 21,
        ]);

        $response = $this->getJson('/articulos');

        $response->assertJson([
            'totales' => ['total' => 1, 'productos' => 1],
        ]);
    }

    // --- Update (US3) ---

    public function test_update_edita_articulo_del_tenant(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $articulo = Articulo::factory()->for($tenant)->producto()->create(['nombre' => 'Nombre Viejo']);
        $this->loginAs($user);

        $response = $this->put("/articulos/{$articulo->id}", [
            'tipo' => 'producto',
            'nombre' => 'Nombre Nuevo',
            'precio' => 15,
            'tipo_impositivo' => 21,
        ]);

        $response->assertRedirect(route('articulos.index'));
        $this->assertDatabaseHas('articulos', ['id' => $articulo->id, 'nombre' => 'Nombre Nuevo']);
    }

    public function test_update_falla_con_datos_invalidos(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $articulo = Articulo::factory()->for($tenant)->producto()->create();
        $this->loginAs($user);

        $response = $this->put("/articulos/{$articulo->id}", [
            'tipo' => 'producto',
            'nombre' => '',
            'precio' => 15,
            'tipo_impositivo' => 21,
        ]);

        $response->assertSessionHasErrors('nombre');
    }

    // --- Destroy (US4) ---

    public function test_destroy_hace_borrado_logico(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $articulo = Articulo::factory()->for($tenant)->producto()->create();
        $this->loginAs($user);

        $response = $this->delete("/articulos/{$articulo->id}");

        $response->assertRedirect(route('articulos.index'));
        $this->assertSoftDeleted($articulo);

        $indexResponse = $this->getJson('/articulos');
        $indexResponse->assertJsonMissing(['nombre' => $articulo->nombre]);
    }

    public function test_metricas_bajan_tras_borrado(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $articulo = Articulo::factory()->for($tenant)->producto()->create();
        $this->loginAs($user);

        $this->delete("/articulos/{$articulo->id}");

        $response = $this->getJson('/articulos');

        $response->assertJson([
            'totales' => ['total' => 0],
        ]);
    }

    public function test_store_via_ajax_devuelve_json_sin_redirigir(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $this->loginAs($user);

        $response = $this->postJson('/articulos', [
            'tipo' => 'producto',
            'nombre' => 'Artículo Ajax',
            'precio' => 10,
            'tipo_impositivo' => 21,
        ]);

        $response->assertCreated();
        $response->assertJsonStructure(['message']);
        $this->assertDatabaseHas('articulos', ['nombre' => 'Artículo Ajax']);
    }

    public function test_destroy_via_ajax_devuelve_json_sin_redirigir(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $articulo = Articulo::factory()->for($tenant)->producto()->create();
        $this->loginAs($user);

        $response = $this->deleteJson("/articulos/{$articulo->id}");

        $response->assertOk();
        $response->assertJsonStructure(['message']);
        $this->assertSoftDeleted($articulo);
    }
}
