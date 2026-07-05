<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_perfil_sin_autenticar_redirige_a_login(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingOnDomain($this->domainFor($tenant));

        $response = $this->get('/perfil');

        $response->assertRedirect('/login');
    }

    public function test_usuario_autenticado_ve_su_nombre_y_email(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Ana Pérez',
            'email' => 'ana@example.com',
            'password' => bcrypt('secret123'),
        ]);
        $this->loginAs($user);

        $response = $this->get('/perfil');

        $response->assertOk();
        $response->assertSee('Ana Pérez');
        $response->assertSee('ana@example.com');
    }

    public function test_cada_usuario_ve_solo_sus_propios_datos(): void
    {
        $tenantA = Tenant::factory()->create();
        $userA = User::factory()->create([
            'tenant_id' => $tenantA->id,
            'name' => 'Usuario A',
            'email' => 'a@example.com',
            'password' => bcrypt('secret123'),
        ]);

        $tenantB = Tenant::factory()->create();
        $userB = User::factory()->create([
            'tenant_id' => $tenantB->id,
            'name' => 'Usuario B',
            'email' => 'b@example.com',
            'password' => bcrypt('secret123'),
        ]);

        $this->loginAs($userA);
        $response = $this->get('/perfil');
        $response->assertOk();
        $response->assertSee('Usuario A');
        $response->assertDontSee('Usuario B');
    }

    public function test_muestra_sin_empresa_cuando_no_hay_tenant(): void
    {
        $user = User::factory()->superAdmin()->create([
            'name' => 'Super Root',
            'password' => bcrypt('secret123'),
        ]);
        $this->loginAs($user);

        $response = $this->get('/perfil');

        $response->assertOk();
        $response->assertSee('Sin empresa');
    }
}
