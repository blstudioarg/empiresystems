<?php

namespace Tests\Feature;

use App\Models\PlantillaEmail;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlantillaEmailAislamientoTenantTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_listado_no_incluye_plantillas_de_otro_tenant(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $userA = User::factory()->create(['tenant_id' => $tenantA->id, 'password' => bcrypt('secret123')]);

        PlantillaEmail::factory()->create(['tenant_id' => $tenantA->id, 'titulo' => 'Plantilla de A']);
        PlantillaEmail::factory()->create(['tenant_id' => $tenantB->id, 'titulo' => 'Plantilla de B']);

        $this->loginAs($userA);

        $response = $this->getJson('/plantillas-email');

        $response->assertOk();
        $response->assertJsonFragment(['titulo' => 'Plantilla de A']);
        $response->assertJsonMissing(['titulo' => 'Plantilla de B']);
    }

    public function test_crear_plantilla_asigna_el_tenant_activo(): void
    {
        $tenantA = Tenant::factory()->create();
        $userA = User::factory()->create(['tenant_id' => $tenantA->id, 'password' => bcrypt('secret123')]);

        $this->loginAs($userA);

        $this->post('/plantillas-email', [
            'titulo' => 'Bienvenida',
            'asunto' => 'Hola',
            'cuerpo' => '<p>Bienvenido</p>',
            'activa' => 1,
        ]);

        $plantilla = PlantillaEmail::where('titulo', 'Bienvenida')->first();
        $this->assertNotNull($plantilla);
        $this->assertEquals($tenantA->id, $plantilla->tenant_id);
    }

    public function test_no_se_puede_editar_una_plantilla_de_otro_tenant(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $userA = User::factory()->create(['tenant_id' => $tenantA->id, 'password' => bcrypt('secret123')]);

        $plantillaB = PlantillaEmail::factory()->create(['tenant_id' => $tenantB->id]);

        $this->loginAs($userA);

        $this->put("/plantillas-email/{$plantillaB->id}", [
            'titulo' => 'Hackeada',
            'asunto' => 'X',
            'cuerpo' => '<p>X</p>',
            'activa' => 1,
        ])->assertNotFound();
    }

    public function test_no_se_puede_eliminar_una_plantilla_de_otro_tenant(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $userA = User::factory()->create(['tenant_id' => $tenantA->id, 'password' => bcrypt('secret123')]);

        $plantillaB = PlantillaEmail::factory()->create(['tenant_id' => $tenantB->id]);

        $this->loginAs($userA);

        $this->delete("/plantillas-email/{$plantillaB->id}")->assertNotFound();
        $this->assertNotSoftDeleted($plantillaB);
    }
}
