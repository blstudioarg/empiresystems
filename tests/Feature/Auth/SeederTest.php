<?php

namespace Tests\Feature\Auth;

use Database\Seeders\AuthSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_crea_super_admin_que_puede_autenticarse(): void
    {
        $this->seed(AuthSeeder::class);

        $response = $this->post('/login', [
            'email' => 'admin@empiresystems.es',
            'password' => AuthSeeder::SUPER_ADMIN_PASSWORD,
        ]);

        $response->assertRedirect('/');
        $this->assertAuthenticated();

        $user = \App\Models\User::where('email', 'admin@empiresystems.es')->first();
        $this->assertTrue($user->isSuperAdmin());
        $this->assertNull($user->tenant_id);
    }

    public function test_seeder_crea_tenant_demo_y_su_admin_que_puede_autenticarse(): void
    {
        $this->seed(AuthSeeder::class);

        $this->actingOnDomain(AuthSeeder::DEMO_TENANT_DOMAIN);

        $response = $this->post('/login', [
            'email' => 'demo@empiresystems.es',
            'password' => AuthSeeder::DEMO_ADMIN_PASSWORD,
        ]);

        $response->assertRedirect('/');
        $this->assertAuthenticated();

        $user = \App\Models\User::where('email', 'demo@empiresystems.es')->first();
        $this->assertNotNull($user->tenant_id);
        $this->assertTrue($user->tenant->activo);
    }

    public function test_seeder_es_idempotente(): void
    {
        $this->seed(AuthSeeder::class);
        $this->seed(AuthSeeder::class);

        $this->assertEquals(1, \App\Models\User::where('email', 'admin@empiresystems.es')->count());
        $this->assertEquals(1, \App\Models\User::where('email', 'demo@empiresystems.es')->count());
        $this->assertEquals(1, \App\Models\Tenant::where('nombre_comercial', 'Empresa Demo SL')->count());
    }
}
