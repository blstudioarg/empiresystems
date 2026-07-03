<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClienteCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_muestra_la_vista_sin_clientes_embebidos(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);

        Cliente::factory()->for($tenant)->empresa()->create(['nombre' => 'Empresa Uno', 'nif' => 'B12345674']);

        $this->loginAs($user);

        $response = $this->get('/clientes');

        $response->assertOk();
        $response->assertViewIs('clientes.index');
    }

    public function test_index_json_devuelve_solo_clientes_del_tenant_activo_con_metricas_correctas(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);

        Cliente::factory()->for($tenant)->empresa()->create(['nombre' => 'Empresa Uno', 'razon_social' => 'Empresa Uno SL', 'nif' => 'B12345674']);
        Cliente::factory()->for($tenant)->particular()->create(['nombre' => 'Particular Uno']);
        Cliente::factory()->for($tenant)->particular()->create(['nombre' => 'Particular Dos']);

        $this->loginAs($user);

        $response = $this->getJson('/clientes');

        $response->assertOk();
        $response->assertJsonCount(3, 'data');
        $response->assertJsonFragment(['nombre' => 'Empresa Uno SL']);
        $response->assertJsonFragment(['nombre' => 'Particular Uno']);
        $response->assertJsonFragment(['nombre' => 'Particular Dos']);
        $response->assertJson([
            'totales' => ['total' => 3, 'empresas' => 1, 'particulares' => 2],
        ]);
    }

    public function test_store_crea_cliente_empresa_valido(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $this->loginAs($user);

        $response = $this->post('/clientes', [
            'tipo' => 'empresa',
            'nombre' => 'Contacto Empresa',
            'razon_social' => 'Empresa SL',
            'nif' => 'B12345674',
            'pais' => 'ES',
        ]);

        $response->assertRedirect(route('clientes.index'));
        $this->assertDatabaseHas('clientes', ['nombre' => 'Contacto Empresa', 'tenant_id' => $tenant->id]);
    }

    public function test_store_crea_cliente_particular_sin_nif(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $this->loginAs($user);

        $response = $this->post('/clientes', [
            'tipo' => 'particular',
            'nombre' => 'Juan Pérez',
            'pais' => 'ES',
        ]);

        $response->assertRedirect(route('clientes.index'));
        $this->assertDatabaseHas('clientes', ['nombre' => 'Juan Pérez', 'nif' => null]);
    }

    public function test_store_falla_sin_nombre(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $this->loginAs($user);

        $response = $this->post('/clientes', [
            'tipo' => 'particular',
            'pais' => 'ES',
        ]);

        $response->assertSessionHasErrors('nombre');
        $this->assertDatabaseCount('clientes', 0);
    }

    public function test_store_empresa_sin_razon_social_ni_nif_falla(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $this->loginAs($user);

        $response = $this->post('/clientes', [
            'tipo' => 'empresa',
            'nombre' => 'Contacto',
            'pais' => 'ES',
        ]);

        $response->assertSessionHasErrors(['razon_social', 'nif']);
        $this->assertDatabaseCount('clientes', 0);
    }

    public function test_store_email_invalido_falla(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $this->loginAs($user);

        $response = $this->post('/clientes', [
            'tipo' => 'particular',
            'nombre' => 'Juan Pérez',
            'email' => 'no-es-un-email',
            'pais' => 'ES',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertDatabaseCount('clientes', 0);
    }

    public function test_store_nif_con_formato_invalido_falla(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $this->loginAs($user);

        $response = $this->post('/clientes', [
            'tipo' => 'empresa',
            'nombre' => 'Contacto',
            'razon_social' => 'Empresa SL',
            'nif' => '12345678X', // dígito de control incorrecto para DNI
            'pais' => 'ES',
        ]);

        $response->assertSessionHasErrors('nif');
        $this->assertDatabaseCount('clientes', 0);
    }

    public function test_store_nif_duplicado_en_mismo_tenant_falla(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        Cliente::factory()->for($tenant)->empresa()->create(['nif' => 'B12345674']);
        $this->loginAs($user);

        $response = $this->post('/clientes', [
            'tipo' => 'empresa',
            'nombre' => 'Otra Empresa',
            'razon_social' => 'Otra SL',
            'nif' => 'B12345674',
            'pais' => 'ES',
        ]);

        $response->assertSessionHasErrors('nif');
        $this->assertDatabaseCount('clientes', 1);
    }

    public function test_store_mismo_nif_en_tenant_distinto_es_permitido(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        Cliente::factory()->for($tenantB)->empresa()->create(['nif' => 'B12345674']);

        $userA = User::factory()->create(['tenant_id' => $tenantA->id, 'password' => bcrypt('secret123')]);
        $this->loginAs($userA);

        $response = $this->post('/clientes', [
            'tipo' => 'empresa',
            'nombre' => 'Empresa en A',
            'razon_social' => 'Empresa A SL',
            'nif' => 'B12345674',
            'pais' => 'ES',
        ]);

        $response->assertRedirect(route('clientes.index'));
        $this->assertDatabaseHas('clientes', ['tenant_id' => $tenantA->id, 'nif' => 'B12345674']);
    }

    public function test_metricas_se_actualizan_tras_alta(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $this->loginAs($user);

        $this->post('/clientes', [
            'tipo' => 'particular',
            'nombre' => 'Nuevo Cliente',
            'pais' => 'ES',
        ]);

        $response = $this->getJson('/clientes');

        $response->assertJson([
            'totales' => ['total' => 1, 'particulares' => 1],
        ]);
    }

    public function test_update_edita_cliente_del_tenant(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $cliente = Cliente::factory()->for($tenant)->particular()->create(['nombre' => 'Nombre Viejo']);
        $this->loginAs($user);

        $response = $this->put("/clientes/{$cliente->id}", [
            'tipo' => 'particular',
            'nombre' => 'Nombre Nuevo',
            'pais' => 'ES',
        ]);

        $response->assertRedirect(route('clientes.index'));
        $this->assertDatabaseHas('clientes', ['id' => $cliente->id, 'nombre' => 'Nombre Nuevo']);
    }

    public function test_update_falla_con_datos_invalidos(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $cliente = Cliente::factory()->for($tenant)->particular()->create();
        $this->loginAs($user);

        $response = $this->put("/clientes/{$cliente->id}", [
            'tipo' => 'particular',
            'nombre' => '',
            'pais' => 'ES',
        ]);

        $response->assertSessionHasErrors('nombre');
    }

    public function test_update_unicidad_nif_ignora_el_propio_cliente(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $cliente = Cliente::factory()->for($tenant)->empresa()->create(['nif' => 'B12345674']);
        $this->loginAs($user);

        $response = $this->put("/clientes/{$cliente->id}", [
            'tipo' => 'empresa',
            'nombre' => $cliente->nombre,
            'razon_social' => $cliente->razon_social,
            'nif' => 'B12345674',
            'pais' => 'ES',
        ]);

        $response->assertSessionDoesntHaveErrors('nif');
        $response->assertRedirect(route('clientes.index'));
    }

    public function test_destroy_hace_borrado_logico(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $cliente = Cliente::factory()->for($tenant)->particular()->create();
        $this->loginAs($user);

        $response = $this->delete("/clientes/{$cliente->id}");

        $response->assertRedirect(route('clientes.index'));
        $this->assertSoftDeleted($cliente);

        $indexResponse = $this->getJson('/clientes');
        $indexResponse->assertJsonMissing(['nombre' => $cliente->nombre]);
    }

    public function test_metricas_bajan_tras_borrado(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $cliente = Cliente::factory()->for($tenant)->particular()->create();
        $this->loginAs($user);

        $this->delete("/clientes/{$cliente->id}");

        $response = $this->getJson('/clientes');

        $response->assertJson([
            'totales' => ['total' => 0],
        ]);
    }

    public function test_store_via_ajax_devuelve_json_sin_redirigir(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $this->loginAs($user);

        $response = $this->postJson('/clientes', [
            'tipo' => 'particular',
            'nombre' => 'Cliente Ajax',
            'pais' => 'ES',
        ]);

        $response->assertCreated();
        $response->assertJsonStructure(['message']);
        $this->assertDatabaseHas('clientes', ['nombre' => 'Cliente Ajax']);
    }

    public function test_store_via_ajax_con_datos_invalidos_devuelve_422_con_errores(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $this->loginAs($user);

        $response = $this->postJson('/clientes', [
            'tipo' => 'particular',
            'pais' => 'ES',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('nombre');
        $this->assertDatabaseCount('clientes', 0);
    }

    public function test_update_via_ajax_devuelve_json_sin_redirigir(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $cliente = Cliente::factory()->for($tenant)->particular()->create();
        $this->loginAs($user);

        $response = $this->putJson("/clientes/{$cliente->id}", [
            'tipo' => 'particular',
            'nombre' => 'Actualizado Ajax',
            'pais' => 'ES',
        ]);

        $response->assertOk();
        $response->assertJsonStructure(['message']);
        $this->assertDatabaseHas('clientes', ['id' => $cliente->id, 'nombre' => 'Actualizado Ajax']);
    }

    public function test_destroy_via_ajax_devuelve_json_sin_redirigir(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $cliente = Cliente::factory()->for($tenant)->particular()->create();
        $this->loginAs($user);

        $response = $this->deleteJson("/clientes/{$cliente->id}");

        $response->assertOk();
        $response->assertJsonStructure(['message']);
        $this->assertSoftDeleted($cliente);
    }
}
