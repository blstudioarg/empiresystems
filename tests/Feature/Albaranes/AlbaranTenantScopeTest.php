<?php

namespace Tests\Feature\Albaranes;

use App\Models\Albaran;
use App\Models\Cliente;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AlbaranTenantScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_listado_de_un_tenant_no_incluye_albaranes_de_otro(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $userA = User::factory()->create(['tenant_id' => $tenantA->id, 'password' => bcrypt('secret123')]);

        Albaran::factory()->create([
            'tenant_id' => $tenantA->id,
            'cliente_id' => Cliente::factory()->create(['tenant_id' => $tenantA->id])->id,
        ]);
        Albaran::factory()->create([
            'tenant_id' => $tenantB->id,
            'cliente_id' => Cliente::factory()->create(['tenant_id' => $tenantB->id])->id,
        ]);

        $this->loginAs($userA);

        $response = $this->getJson('/albaranes');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }

    public function test_no_se_puede_ver_un_albaran_de_otro_tenant(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $userA = User::factory()->create(['tenant_id' => $tenantA->id, 'password' => bcrypt('secret123')]);
        $albaranB = Albaran::factory()->create([
            'tenant_id' => $tenantB->id,
            'cliente_id' => Cliente::factory()->create(['tenant_id' => $tenantB->id])->id,
        ]);

        $this->loginAs($userA);

        $response = $this->get("/albaranes/{$albaranB->id}");

        $response->assertNotFound();
    }

    public function test_no_se_puede_eliminar_un_albaran_de_otro_tenant(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $userA = User::factory()->create(['tenant_id' => $tenantA->id, 'password' => bcrypt('secret123')]);
        $albaranB = Albaran::factory()->create([
            'tenant_id' => $tenantB->id,
            'cliente_id' => Cliente::factory()->create(['tenant_id' => $tenantB->id])->id,
        ]);

        $this->loginAs($userA);

        $response = $this->delete("/albaranes/{$albaranB->id}");

        $response->assertNotFound();
        $this->assertNotSoftDeleted($albaranB);
    }
}
