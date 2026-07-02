<?php

namespace Tests\Feature\Auth;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_con_credenciales_validas_autentica_y_redirige(): void
    {
        $user = User::factory()->create([
            'email' => 'user@example.com',
            'password' => bcrypt('secret123'),
        ]);

        $response = $this->post('/login', [
            'email' => 'user@example.com',
            'password' => 'secret123',
        ]);

        $response->assertRedirect('/');
        $this->assertAuthenticatedAs($user);
    }

    public function test_email_inexistente_devuelve_mensaje_generico(): void
    {
        $response = $this->post('/login', [
            'email' => 'noexiste@example.com',
            'password' => 'cualquiera',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_password_incorrecta_devuelve_el_mismo_mensaje_generico(): void
    {
        $user = User::factory()->create([
            'email' => 'user@example.com',
            'password' => bcrypt('secret123'),
        ]);

        $response = $this->post('/login', [
            'email' => 'user@example.com',
            'password' => 'incorrecta',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();

        $errorInexistente = session('errors')->get('email');

        $response2 = $this->post('/login', [
            'email' => 'noexiste@example.com',
            'password' => 'cualquiera',
        ]);

        $this->assertEquals($errorInexistente, session('errors')->get('email'));
    }

    public function test_usuario_inactivo_no_puede_autenticarse(): void
    {
        User::factory()->inactive()->create([
            'email' => 'inactivo@example.com',
            'password' => bcrypt('secret123'),
        ]);

        $response = $this->post('/login', [
            'email' => 'inactivo@example.com',
            'password' => 'secret123',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_usuario_de_tenant_inactivo_no_puede_autenticarse(): void
    {
        $tenant = Tenant::factory()->inactive()->create();

        User::factory()->create([
            'tenant_id' => $tenant->id,
            'email' => 'user@example.com',
            'password' => bcrypt('secret123'),
        ]);

        $response = $this->post('/login', [
            'email' => 'user@example.com',
            'password' => 'secret123',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_email_vacio_o_mal_formado_falla_validacion(): void
    {
        $response = $this->post('/login', [
            'email' => '',
            'password' => 'secret123',
        ]);
        $response->assertSessionHasErrors('email');

        $response = $this->post('/login', [
            'email' => 'no-es-un-email',
            'password' => 'secret123',
        ]);
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_password_vacia_falla_validacion(): void
    {
        $response = $this->post('/login', [
            'email' => 'user@example.com',
            'password' => '',
        ]);

        $response->assertSessionHasErrors('password');
        $this->assertGuest();
    }

    public function test_sexto_intento_fallido_activa_throttling(): void
    {
        RateLimiter::clear('login|noexiste@example.com|127.0.0.1');

        $user = User::factory()->create([
            'email' => 'user@example.com',
            'password' => bcrypt('secret123'),
        ]);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', [
                'email' => 'user@example.com',
                'password' => 'incorrecta',
            ]);
        }

        $response = $this->post('/login', [
            'email' => 'user@example.com',
            'password' => 'incorrecta',
        ]);

        $response->assertSessionHasErrors('email');
        $errors = session('errors')->get('email');
        $this->assertStringContainsString('Demasiados intentos', $errors[0]);
    }

    public function test_login_con_remember_persiste_cookie(): void
    {
        User::factory()->create([
            'email' => 'user@example.com',
            'password' => bcrypt('secret123'),
        ]);

        $response = $this->post('/login', [
            'email' => 'user@example.com',
            'password' => 'secret123',
            'remember' => 'on',
        ]);

        $response->assertRedirect('/');
        $response->assertCookie(\Illuminate\Support\Facades\Auth::getRecallerName());
    }

    public function test_login_sin_remember_no_genera_cookie_recordable(): void
    {
        User::factory()->create([
            'email' => 'user@example.com',
            'password' => bcrypt('secret123'),
        ]);

        $response = $this->post('/login', [
            'email' => 'user@example.com',
            'password' => 'secret123',
        ]);

        $response->assertRedirect('/');
        $response->assertCookieMissing(\Illuminate\Support\Facades\Auth::getRecallerName());
    }
}
