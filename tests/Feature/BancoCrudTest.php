<?php

namespace Tests\Feature;

use App\Models\Banco;
use App\Models\CuentaBancaria;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BancoCrudTest extends TestCase
{
    use RefreshDatabase;

    private function login(Tenant $tenant): void
    {
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $this->loginAs($user);
    }

    public function test_index_solo_devuelve_bancos_del_tenant_activo(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $bancoA = Banco::create(['tenant_id' => $tenantA->id, 'nombre' => 'Banco A']);
        $bancoB = Banco::create(['tenant_id' => $tenantB->id, 'nombre' => 'Banco B']);

        $this->login($tenantA);

        $ids = collect($this->getJson('/bancos')->assertOk()->json('data'))->pluck('id');

        $this->assertTrue($ids->contains($bancoA->id));
        $this->assertFalse($ids->contains($bancoB->id));
    }

    public function test_store_crea_el_banco_bajo_el_tenant_activo(): void
    {
        $tenant = Tenant::factory()->create();
        $this->login($tenant);

        $this->postJson('/bancos', ['nombre' => 'Banco Nuevo'])->assertCreated();

        $this->assertDatabaseHas('bancos', [
            'tenant_id' => $tenant->id,
            'nombre' => 'Banco Nuevo',
        ]);
    }

    public function test_store_rechaza_nombre_duplicado_en_el_mismo_tenant(): void
    {
        $tenant = Tenant::factory()->create();
        Banco::create(['tenant_id' => $tenant->id, 'nombre' => 'Repetido']);
        $this->login($tenant);

        $this->postJson('/bancos', ['nombre' => 'Repetido'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('nombre');
    }

    public function test_mismo_nombre_permitido_en_tenants_distintos(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        Banco::create(['tenant_id' => $tenantB->id, 'nombre' => 'Compartido']);

        $this->login($tenantA);

        $this->postJson('/bancos', ['nombre' => 'Compartido'])->assertCreated();
    }

    public function test_update_renombra_el_banco(): void
    {
        $tenant = Tenant::factory()->create();
        $banco = Banco::create(['tenant_id' => $tenant->id, 'nombre' => 'Antiguo']);
        $this->login($tenant);

        $this->putJson("/bancos/{$banco->id}", ['nombre' => 'Renombrado'])->assertOk();

        $this->assertDatabaseHas('bancos', ['id' => $banco->id, 'nombre' => 'Renombrado']);
    }

    public function test_no_se_puede_actualizar_banco_de_otro_tenant(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $bancoB = Banco::create(['tenant_id' => $tenantB->id, 'nombre' => 'De B']);

        $this->login($tenantA);

        $this->putJson("/bancos/{$bancoB->id}", ['nombre' => 'Hackeado'])->assertNotFound();
        $this->assertDatabaseHas('bancos', ['id' => $bancoB->id, 'nombre' => 'De B']);
    }

    public function test_destroy_elimina_el_banco(): void
    {
        $tenant = Tenant::factory()->create();
        $banco = Banco::create(['tenant_id' => $tenant->id, 'nombre' => 'Borrable']);
        $this->login($tenant);

        $this->deleteJson("/bancos/{$banco->id}")->assertOk();
        $this->assertDatabaseMissing('bancos', ['id' => $banco->id]);
    }

    public function test_no_se_puede_eliminar_banco_en_uso(): void
    {
        $tenant = Tenant::factory()->create();
        $banco = Banco::create(['tenant_id' => $tenant->id, 'nombre' => 'En uso']);
        CuentaBancaria::factory()->create(['tenant_id' => $tenant->id, 'banco_id' => $banco->id]);
        $this->login($tenant);

        $this->deleteJson("/bancos/{$banco->id}")->assertStatus(422);
        $this->assertDatabaseHas('bancos', ['id' => $banco->id]);
    }
}
