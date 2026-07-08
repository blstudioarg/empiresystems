<?php

namespace Tests\Feature\Leads;

use App\Models\Lead;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeadTenantScopeTest extends TestCase
{
    use RefreshDatabase;

    private function payloadValido(): array
    {
        return [
            'nombre' => 'Juan Pérez',
            'email' => 'juan@example.com',
        ];
    }

    public function test_el_listado_de_un_tenant_no_incluye_leads_de_otro(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $userA = User::factory()->create(['tenant_id' => $tenantA->id, 'password' => bcrypt('secret123')]);

        Lead::factory()->create(['tenant_id' => $tenantA->id]);
        Lead::factory()->create(['tenant_id' => $tenantB->id]);

        $this->loginAs($userA);

        $response = $this->getJson('/leads');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }

    public function test_no_se_puede_ver_un_lead_de_otro_tenant(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $userA = User::factory()->create(['tenant_id' => $tenantA->id, 'password' => bcrypt('secret123')]);
        $leadB = Lead::factory()->create(['tenant_id' => $tenantB->id]);

        $this->loginAs($userA);

        $response = $this->get("/leads/{$leadB->id}");

        $response->assertNotFound();
    }

    public function test_no_se_puede_editar_un_lead_de_otro_tenant(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $userA = User::factory()->create(['tenant_id' => $tenantA->id, 'password' => bcrypt('secret123')]);
        $leadB = Lead::factory()->create(['tenant_id' => $tenantB->id]);

        $this->loginAs($userA);

        $response = $this->put("/leads/{$leadB->id}", $this->payloadValido());

        $response->assertNotFound();
    }

    public function test_no_se_puede_eliminar_un_lead_de_otro_tenant(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $userA = User::factory()->create(['tenant_id' => $tenantA->id, 'password' => bcrypt('secret123')]);
        $leadB = Lead::factory()->create(['tenant_id' => $tenantB->id]);

        $this->loginAs($userA);

        $response = $this->delete("/leads/{$leadB->id}");

        $response->assertNotFound();
        $this->assertNotSoftDeleted($leadB);
    }
}
