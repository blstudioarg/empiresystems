<?php

namespace Tests\Feature\Profile;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProfilePasswordTest extends TestCase
{
    use RefreshDatabase;

    private function crearUsuario(): User
    {
        $tenant = Tenant::factory()->create();

        return User::factory()->create([
            'tenant_id' => $tenant->id,
            'password' => bcrypt('secret123'),
        ]);
    }

    public function test_cambio_de_contrasena_exitoso(): void
    {
        $user = $this->crearUsuario();
        $this->loginAs($user);

        $response = $this->putJson('/perfil/contrasena', [
            'contrasena_actual' => 'secret123',
            'password' => 'nueva12345',
            'password_confirmation' => 'nueva12345',
        ]);

        $response->assertOk();
        $this->assertTrue(Hash::check('nueva12345', $user->fresh()->password));
    }

    public function test_error_si_la_contrasena_actual_es_incorrecta(): void
    {
        $user = $this->crearUsuario();
        $this->loginAs($user);

        $response = $this->putJson('/perfil/contrasena', [
            'contrasena_actual' => 'incorrecta',
            'password' => 'nueva12345',
            'password_confirmation' => 'nueva12345',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('contrasena_actual');
    }

    public function test_error_si_la_confirmacion_no_coincide(): void
    {
        $user = $this->crearUsuario();
        $this->loginAs($user);

        $response = $this->putJson('/perfil/contrasena', [
            'contrasena_actual' => 'secret123',
            'password' => 'nueva12345',
            'password_confirmation' => 'otradistinta',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('password');
    }

    public function test_error_si_no_cumple_minimo_de_8(): void
    {
        $user = $this->crearUsuario();
        $this->loginAs($user);

        $response = $this->putJson('/perfil/contrasena', [
            'contrasena_actual' => 'secret123',
            'password' => 'abc123',
            'password_confirmation' => 'abc123',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('password');
    }

    public function test_invalida_sesiones_de_otros_dispositivos_preservando_la_actual(): void
    {
        // El driver de sesión en tests es "array" por defecto (phpunit.xml), que no persiste
        // filas en `sessions` ni mantiene el id estable entre requests sin cookie real. Se fuerza
        // aquí el driver "database" (el que usa producción, config/session.php) para que el
        // propio framework escriba la fila de la sesión actual al terminar el request, tal como
        // ocurriría de verdad. Se usa `actingAs()` (sin pasar por /login) para que este PUT sea
        // el primer request real que toca el handler de sesión de BD en el test: así escribe con
        // INSERT (no UPDATE) y la fila de la sesión actual queda persistida de verdad.
        config(['session.driver' => 'database']);

        $tenant = Tenant::factory()->create();
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'password' => bcrypt('secret123'),
        ]);
        $this->actingOnDomain($this->domainFor($tenant));
        $this->actingAs($user);

        DB::table('sessions')->insert([
            'id' => 'otro-dispositivo-session-id',
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Otro dispositivo',
            'payload' => base64_encode('x'),
            'last_activity' => time(),
        ]);

        $response = $this->putJson('/perfil/contrasena', [
            'contrasena_actual' => 'secret123',
            'password' => 'nueva12345',
            'password_confirmation' => 'nueva12345',
        ]);

        $response->assertOk();

        $this->assertDatabaseMissing('sessions', ['id' => 'otro-dispositivo-session-id']);
        // Solo debe quedar la fila que el propio framework escribe para la sesión actual al
        // terminar este mismo request (se persiste después del borrado selectivo del controller).
        $this->assertDatabaseCount('sessions', 1);
    }
}
