<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RouteProtectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_que_pide_ruta_interna_es_redirigido_a_login(): void
    {
        $response = $this->get('/');

        $response->assertRedirect('/login');
    }

    public function test_logout_termina_la_sesion_y_redirige_a_login(): void
    {
        $user = User::factory()->create([
            'email' => 'user@example.com',
            'password' => bcrypt('secret123'),
        ]);

        $this->actingOnDomain($this->domainFor($user->tenant()->first()));

        $this->post('/login', [
            'email' => 'user@example.com',
            'password' => 'secret123',
        ]);
        $this->assertAuthenticatedAs($user);

        $response = $this->post('/logout');

        $response->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_tras_logout_una_ruta_interna_vuelve_a_redirigir_a_login(): void
    {
        $user = User::factory()->create([
            'email' => 'user@example.com',
            'password' => bcrypt('secret123'),
        ]);

        $this->actingOnDomain($this->domainFor($user->tenant()->first()));

        $this->post('/login', [
            'email' => 'user@example.com',
            'password' => 'secret123',
        ]);
        $this->post('/logout');

        $response = $this->get('/');

        $response->assertRedirect('/login');
    }
}
