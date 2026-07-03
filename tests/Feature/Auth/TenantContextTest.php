<?php

namespace Tests\Feature\Auth;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantContextTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_inicializa_el_contexto_con_el_tenant_del_usuario(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'email' => 'user@example.com',
            'password' => bcrypt('secret123'),
        ]);

        $this->loginAs($user);

        $this->get('/');

        $this->assertTrue(tenancy()->initialized);
        $this->assertEquals($tenant->id, tenant('id'));
    }

    public function test_contexto_no_se_mezcla_entre_tenants_distintos(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $userA = User::factory()->create([
            'tenant_id' => $tenantA->id,
            'email' => 'usera@example.com',
            'password' => bcrypt('secret123'),
        ]);

        User::factory()->create([
            'tenant_id' => $tenantB->id,
            'email' => 'userb@example.com',
            'password' => bcrypt('secret123'),
        ]);

        $this->loginAs($userA);
        $this->get('/');

        $this->assertEquals($tenantA->id, tenant('id'));
        $this->assertNotEquals($tenantB->id, tenant('id'));
    }

    public function test_super_admin_no_inicializa_contexto_de_tenant(): void
    {
        User::factory()->superAdmin()->create([
            'email' => 'superadmin@example.com',
            'password' => bcrypt('secret123'),
        ]);

        $this->post('/login', [
            'email' => 'superadmin@example.com',
            'password' => 'secret123',
        ]);

        $this->get('/');

        $this->assertFalse(tenancy()->initialized);
    }

    public function test_tenant_desactivado_a_mitad_de_sesion_desloguea_al_usuario(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'email' => 'user@example.com',
            'password' => bcrypt('secret123'),
        ]);

        $this->loginAs($user);
        $this->assertAuthenticatedAs($user);

        $tenant->update(['activo' => false]);

        $response = $this->get('/');

        $response->assertRedirect('/login');
        $this->assertGuest();
    }
}
