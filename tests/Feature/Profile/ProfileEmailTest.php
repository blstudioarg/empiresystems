<?php

namespace Tests\Feature\Profile;

use App\Mail\VerificarCambioEmail;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ProfileEmailTest extends TestCase
{
    use RefreshDatabase;

    private function crearUsuario(): User
    {
        $tenant = Tenant::factory()->create();

        return User::factory()->create([
            'tenant_id' => $tenant->id,
            'email' => 'actual@example.com',
            'password' => bcrypt('secret123'),
        ]);
    }

    public function test_solicitar_cambio_de_email_requiere_contrasena_correcta(): void
    {
        Mail::fake();
        $user = $this->crearUsuario();
        $this->loginAs($user);

        $response = $this->postJson('/perfil/email', [
            'contrasena_actual' => 'incorrecta',
            'nuevo_email' => 'nuevo@example.com',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('contrasena_actual');
        Mail::assertNothingSent();
    }

    public function test_solicitar_cambio_envia_email_y_deja_solicitud_pendiente(): void
    {
        Mail::fake();
        $user = $this->crearUsuario();
        $this->loginAs($user);

        $response = $this->postJson('/perfil/email', [
            'contrasena_actual' => 'secret123',
            'nuevo_email' => 'nuevo@example.com',
        ]);

        $response->assertOk();
        $user->refresh();
        $this->assertSame('nuevo@example.com', $user->pending_email);
        $this->assertNotNull($user->pending_email_token);
        $this->assertTrue($user->pending_email_expires_at->isFuture());
        $this->assertSame('actual@example.com', $user->email);

        Mail::assertSent(VerificarCambioEmail::class, fn (VerificarCambioEmail $mail) => $mail->hasTo('nuevo@example.com'));
    }

    public function test_confirmar_con_enlace_valido_aplica_el_cambio(): void
    {
        Mail::fake();
        $user = $this->crearUsuario();
        $this->loginAs($user);

        $this->postJson('/perfil/email', [
            'contrasena_actual' => 'secret123',
            'nuevo_email' => 'nuevo@example.com',
        ])->assertOk();

        $urlCapturada = null;
        Mail::assertSent(VerificarCambioEmail::class, function (VerificarCambioEmail $mail) use (&$urlCapturada) {
            $urlCapturada = $mail->urlVerificacion;

            return true;
        });

        $response = $this->get($urlCapturada);

        $response->assertRedirect(route('profile.show'));
        $user->refresh();
        $this->assertSame('nuevo@example.com', $user->email);
        $this->assertNull($user->pending_email);
        $this->assertNull($user->pending_email_token);
        $this->assertNull($user->pending_email_expires_at);
    }

    public function test_enlace_vencido_no_aplica_el_cambio(): void
    {
        Mail::fake();
        $user = $this->crearUsuario();
        $this->loginAs($user);

        $this->postJson('/perfil/email', [
            'contrasena_actual' => 'secret123',
            'nuevo_email' => 'nuevo@example.com',
        ])->assertOk();

        $urlCapturada = null;
        Mail::assertSent(VerificarCambioEmail::class, function (VerificarCambioEmail $mail) use (&$urlCapturada) {
            $urlCapturada = $mail->urlVerificacion;

            return true;
        });

        $this->travel(25)->hours();

        $response = $this->get($urlCapturada);

        $response->assertRedirect(route('profile.show'));
        $user->refresh();
        $this->assertSame('actual@example.com', $user->email);
        $this->assertSame('nuevo@example.com', $user->pending_email);
    }

    public function test_cancelar_limpia_el_estado_pendiente(): void
    {
        Mail::fake();
        $user = $this->crearUsuario();
        $this->loginAs($user);

        $this->postJson('/perfil/email', [
            'contrasena_actual' => 'secret123',
            'nuevo_email' => 'nuevo@example.com',
        ])->assertOk();

        $response = $this->deleteJson('/perfil/email/pendiente');

        $response->assertOk();
        $user->refresh();
        $this->assertNull($user->pending_email);
        $this->assertNull($user->pending_email_token);
        $this->assertNull($user->pending_email_expires_at);
    }

    public function test_cancelar_sin_solicitud_pendiente_da_404(): void
    {
        $user = $this->crearUsuario();
        $this->loginAs($user);

        $response = $this->deleteJson('/perfil/email/pendiente');

        $response->assertStatus(404);
    }

    public function test_reenviar_invalida_el_enlace_anterior(): void
    {
        Mail::fake();
        $user = $this->crearUsuario();
        $this->loginAs($user);

        $this->postJson('/perfil/email', [
            'contrasena_actual' => 'secret123',
            'nuevo_email' => 'nuevo@example.com',
        ])->assertOk();

        $urlAnterior = null;
        Mail::assertSent(VerificarCambioEmail::class, function (VerificarCambioEmail $mail) use (&$urlAnterior) {
            $urlAnterior = $mail->urlVerificacion;

            return true;
        });

        $response = $this->postJson('/perfil/email/pendiente/reenviar');
        $response->assertOk();

        // El enlace anterior usaba un token ya reemplazado: el nuevo token no matchea, se rechaza.
        $confirmacion = $this->get($urlAnterior);
        $confirmacion->assertRedirect(route('profile.show'));

        $user->refresh();
        $this->assertSame('actual@example.com', $user->email);
        $this->assertSame('nuevo@example.com', $user->pending_email);

        Mail::assertSent(VerificarCambioEmail::class, 2);
    }

    public function test_error_si_el_email_ya_esta_en_uso_en_el_mismo_tenant(): void
    {
        Mail::fake();
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'email' => 'actual@example.com',
            'password' => bcrypt('secret123'),
        ]);
        User::factory()->create([
            'tenant_id' => $tenant->id,
            'email' => 'ocupado@example.com',
        ]);
        $this->loginAs($user);

        $response = $this->postJson('/perfil/email', [
            'contrasena_actual' => 'secret123',
            'nuevo_email' => 'ocupado@example.com',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('nuevo_email');
    }
}
