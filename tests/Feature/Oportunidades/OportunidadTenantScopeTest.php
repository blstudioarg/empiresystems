<?php

namespace Tests\Feature\Oportunidades;

use App\Models\Cliente;
use App\Models\Oportunidad;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OportunidadTenantScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_listado_de_un_tenant_no_incluye_oportunidades_de_otro(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $userA = User::factory()->create(['tenant_id' => $tenantA->id, 'password' => bcrypt('secret123')]);

        $clienteA = Cliente::factory()->create(['tenant_id' => $tenantA->id]);
        $clienteB = Cliente::factory()->create(['tenant_id' => $tenantB->id]);

        Oportunidad::factory()->create(['tenant_id' => $tenantA->id, 'cliente_id' => $clienteA->id]);
        Oportunidad::factory()->create(['tenant_id' => $tenantB->id, 'cliente_id' => $clienteB->id]);

        $this->loginAs($userA);

        $response = $this->getJson('/oportunidades');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }

    public function test_no_se_puede_crear_oportunidad_con_cliente_de_otro_tenant(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $userA = User::factory()->create(['tenant_id' => $tenantA->id, 'password' => bcrypt('secret123')]);
        $clienteB = Cliente::factory()->create(['tenant_id' => $tenantB->id]);

        $this->loginAs($userA);

        $response = $this->post('/oportunidades', [
            'titulo' => 'Venta software',
            'cliente_id' => $clienteB->id,
        ]);

        $response->assertSessionHasErrors('cliente_id');
        $this->assertDatabaseCount('oportunidades', 0);
    }

    public function test_no_se_puede_ver_una_oportunidad_de_otro_tenant(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $userA = User::factory()->create(['tenant_id' => $tenantA->id, 'password' => bcrypt('secret123')]);
        $clienteB = Cliente::factory()->create(['tenant_id' => $tenantB->id]);
        $oportunidadB = Oportunidad::factory()->create(['tenant_id' => $tenantB->id, 'cliente_id' => $clienteB->id]);

        $this->loginAs($userA);

        $response = $this->get("/oportunidades/{$oportunidadB->id}");

        $response->assertNotFound();
    }
}
