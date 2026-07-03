<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Stancl\Tenancy\Database\Models\Domain;
use Tests\TestCase;

class TenantDomainResolutionTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_host_resuelve_el_tenant_correcto_sin_fuga_entre_tenants(): void
    {
        $tenantA = Tenant::factory()->create();
        Domain::create(['domain' => 'a.test', 'tenant_id' => $tenantA->id]);
        $userA = User::factory()->create(['tenant_id' => $tenantA->id, 'email' => 'a@example.com', 'password' => bcrypt('secret123')]);

        $tenantB = Tenant::factory()->create();
        Domain::create(['domain' => 'b.test', 'tenant_id' => $tenantB->id]);
        User::factory()->create(['tenant_id' => $tenantB->id, 'email' => 'b@example.com', 'password' => bcrypt('secret123')]);

        Cliente::factory()->create(['tenant_id' => $tenantA->id, 'nombre' => 'Cliente de A']);
        Cliente::factory()->create(['tenant_id' => $tenantB->id, 'nombre' => 'Cliente de B']);

        $this->actingAs($userA);
        $response = $this->get('http://a.test/clientes', ['Accept' => 'application/json']);
        $response->assertOk();
        $response->assertJsonFragment(['nombre' => 'Cliente de A']);
        $response->assertJsonMissing(['nombre' => 'Cliente de B']);

        $this->assertEquals($tenantA->id, tenant('id'));
    }

    public function test_host_no_central_y_sin_dominio_registrado_devuelve_404(): void
    {
        $response = $this->get('http://z.test/');

        $response->assertNotFound();
    }

    public function test_gate_login_dominio_rechaza_usuario_de_otro_tenant_y_acepta_el_propio(): void
    {
        $tenantA = Tenant::factory()->create();
        Domain::create(['domain' => 'a.test', 'tenant_id' => $tenantA->id]);

        $tenantB = Tenant::factory()->create();
        Domain::create(['domain' => 'b.test', 'tenant_id' => $tenantB->id]);
        User::factory()->create(['tenant_id' => $tenantB->id, 'email' => 'b@example.com', 'password' => bcrypt('secret123')]);

        // Usuario de B intentando loguear desde el dominio de A -> rechazado.
        $response = $this->post('http://a.test/login', [
            'email' => 'b@example.com',
            'password' => 'secret123',
        ]);
        $response->assertSessionHasErrors('email');
        $this->assertGuest();

        // Mismo usuario desde su propio dominio -> OK.
        $response = $this->post('http://b.test/login', [
            'email' => 'b@example.com',
            'password' => 'secret123',
        ]);
        $response->assertRedirect('/');
        $this->assertAuthenticated();
    }

    public function test_super_admin_solo_autentica_valido_en_dominio_central(): void
    {
        $tenantA = Tenant::factory()->create();
        Domain::create(['domain' => 'a.test', 'tenant_id' => $tenantA->id]);

        User::factory()->superAdmin()->create(['email' => 'superadmin@example.com', 'password' => bcrypt('secret123')]);

        // Desde el dominio de un tenant -> rechazado.
        $response = $this->post('http://a.test/login', [
            'email' => 'superadmin@example.com',
            'password' => 'secret123',
        ]);
        $response->assertSessionHasErrors('email');
        $this->assertGuest();

        // Desde el dominio central -> OK.
        $response = $this->post('http://localhost/login', [
            'email' => 'superadmin@example.com',
            'password' => 'secret123',
        ]);
        $response->assertRedirect('/');
        $this->assertAuthenticated();
    }
}
