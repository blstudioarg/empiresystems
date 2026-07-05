<?php

namespace Tests\Feature;

use App\Models\Carpeta;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CarpetaAislamientoTenantTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_listado_de_un_tenant_no_incluye_carpetas_de_otro(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $userA = User::factory()->create(['tenant_id' => $tenantA->id, 'password' => bcrypt('secret123')]);

        Carpeta::factory()->create(['tenant_id' => $tenantA->id, 'nombre' => 'De A']);
        Carpeta::factory()->create(['tenant_id' => $tenantB->id, 'nombre' => 'De B']);

        $this->loginAs($userA);

        $response = $this->getJson('/archivos');

        $response->assertOk();
        $response->assertJsonFragment(['nombre' => 'De A']);
        $response->assertJsonMissing(['nombre' => 'De B']);
    }

    public function test_no_se_puede_crear_una_carpeta_con_parent_id_de_otro_tenant(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $userA = User::factory()->create(['tenant_id' => $tenantA->id, 'password' => bcrypt('secret123')]);

        $carpetaB = Carpeta::factory()->create(['tenant_id' => $tenantB->id]);

        $this->loginAs($userA);

        $response = $this->post('/archivos/carpetas', [
            'nombre' => 'Intento',
            'parent_id' => $carpetaB->id,
        ], ['Accept' => 'application/json']);

        $response->assertStatus(422);
        $this->assertDatabaseMissing('carpetas', ['tenant_id' => $tenantA->id, 'nombre' => 'Intento']);
    }

    public function test_no_se_puede_navegar_a_una_carpeta_de_otro_tenant(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $userA = User::factory()->create(['tenant_id' => $tenantA->id, 'password' => bcrypt('secret123')]);

        $carpetaB = Carpeta::factory()->create(['tenant_id' => $tenantB->id]);

        $this->loginAs($userA);

        $this->getJson("/archivos?carpeta={$carpetaB->id}")->assertNotFound();
    }

    public function test_no_se_puede_mover_una_carpeta_propia_usando_parent_id_de_otro_tenant(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $userA = User::factory()->create(['tenant_id' => $tenantA->id, 'password' => bcrypt('secret123')]);

        $carpetaA = Carpeta::factory()->create(['tenant_id' => $tenantA->id, 'nombre' => 'De A', 'parent_id' => null]);
        $carpetaB = Carpeta::factory()->create(['tenant_id' => $tenantB->id]);

        $this->loginAs($userA);

        $response = $this->put("/archivos/carpetas/{$carpetaA->id}", ['parent_id' => $carpetaB->id], ['Accept' => 'application/json']);

        $response->assertStatus(422);
        $this->assertDatabaseHas('carpetas', ['id' => $carpetaA->id, 'parent_id' => null]);
    }

    public function test_no_se_puede_mover_una_carpeta_de_otro_tenant(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $userA = User::factory()->create(['tenant_id' => $tenantA->id, 'password' => bcrypt('secret123')]);

        $destinoA = Carpeta::factory()->create(['tenant_id' => $tenantA->id]);
        $carpetaB = Carpeta::factory()->create(['tenant_id' => $tenantB->id, 'parent_id' => null]);

        $this->loginAs($userA);

        $this->put("/archivos/carpetas/{$carpetaB->id}", ['parent_id' => $destinoA->id])->assertNotFound();
        $this->assertDatabaseHas('carpetas', ['id' => $carpetaB->id, 'parent_id' => null]);
    }
}
