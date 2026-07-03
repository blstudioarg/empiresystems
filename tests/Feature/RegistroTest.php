<?php

namespace Tests\Feature;

use App\Enums\EstadoUsuario;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistroTest extends TestCase
{
    use RefreshDatabase;

    public function test_registro_con_datos_validos_crea_usuario_pendiente(): void
    {
        $tenant = Tenant::factory()->create();

        $response = $this->post('/registro', [
            'name' => 'Nuevo Solicitante',
            'email' => 'nuevo@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('users', [
            'email' => 'nuevo@example.com',
            'estado' => EstadoUsuario::Pendiente->value,
            'activo' => false,
            'tenant_id' => $tenant->id,
        ]);
    }

    public function test_registro_con_email_duplicado_no_crea_usuario(): void
    {
        Tenant::factory()->create();
        User::factory()->create(['email' => 'duplicado@example.com']);

        $response = $this->post('/registro', [
            'name' => 'Otro',
            'email' => 'duplicado@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertSame(1, User::where('email', 'duplicado@example.com')->count());
    }

    public function test_usuario_pendiente_no_puede_iniciar_sesion(): void
    {
        Tenant::factory()->create();
        $user = User::factory()->pendiente()->create([
            'password' => bcrypt('secret123'),
        ]);

        $this->actingOnDomain($this->domainFor($user->tenant()->first()));

        $response = $this->from('/login')->post('/login', [
            'email' => $user->email,
            'password' => 'secret123',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertStringContainsString(
            'no está aprobada',
            session('errors')->first('email')
        );
        $this->assertGuest();
    }
}
