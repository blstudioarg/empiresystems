<?php

namespace Tests\Feature;

use App\Mail\CampanaMail;
use App\Models\Campana;
use App\Models\CampanaDestinatario;
use App\Models\Cliente;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class CampanaEnvioTandaTest extends TestCase
{
    use RefreshDatabase;

    private function configurarEmail(): void
    {
        $this->put('/configuracion/email', [
            'smtp_host' => 'smtp.hostinger.com',
            'smtp_port' => 465,
            'smtp_encryption' => 'ssl',
            'smtp_usuario' => 'campanas@empresa.test',
            'smtp_password' => 'secreto-smtp',
            'remitente' => 'campanas@empresa.test',
            'remitente_nombre' => 'Empresa Demo',
            'responder_a' => '',
        ]);
    }

    public function test_crear_campana_materializa_destinatarios_y_marca_sin_email_como_fallido(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $conEmail = Cliente::factory()->create(['tenant_id' => $tenant->id, 'email' => 'uno@destino.test']);
        $sinEmail = Cliente::factory()->create(['tenant_id' => $tenant->id, 'email' => null]);

        $this->loginAs($user);
        $this->configurarEmail();

        $this->post('/campanas', [
            'asunto' => 'Novedades',
            'cuerpo' => '<p>Hola</p>',
            'cliente_ids' => [$conEmail->id, $sinEmail->id],
        ])->assertRedirect();

        $campana = Campana::firstOrFail();
        $this->assertEquals(2, $campana->total_destinatarios);

        $this->assertDatabaseHas('campana_destinatarios', [
            'campana_id' => $campana->id,
            'cliente_id' => $conEmail->id,
            'estado' => 'pendiente',
        ]);
        $this->assertDatabaseHas('campana_destinatarios', [
            'campana_id' => $campana->id,
            'cliente_id' => $sinEmail->id,
            'estado' => 'fallido',
            'error' => 'Sin email',
        ]);
    }

    public function test_enviar_tanda_marca_enviado_y_recalcula_contadores(): void
    {
        Mail::fake();

        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $cliente = Cliente::factory()->create(['tenant_id' => $tenant->id, 'email' => 'uno@destino.test']);

        $this->loginAs($user);
        $this->configurarEmail();

        $this->post('/campanas', [
            'asunto' => 'Novedades',
            'cuerpo' => '<p>Hola</p>',
            'cliente_ids' => [$cliente->id],
        ]);

        $campana = Campana::firstOrFail();
        $destinatario = CampanaDestinatario::firstOrFail();

        $response = $this->postJson("/campanas/{$campana->id}/enviar-tanda", [
            'destinatario_ids' => [$destinatario->id],
        ]);

        $response->assertOk();
        $response->assertJsonFragment(['estado' => 'enviado']);

        Mail::assertSent(CampanaMail::class, fn (CampanaMail $mail) => $mail->hasTo('uno@destino.test'));

        $campana->refresh();
        $this->assertEquals(1, $campana->enviados);
        $this->assertEquals(0, $campana->fallidos);
        $this->assertEquals('finalizada', $campana->estado->value);
    }

    public function test_un_fallo_de_envio_no_aborta_el_resto_de_la_tanda(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $ok = Cliente::factory()->create(['tenant_id' => $tenant->id, 'email' => 'ok@destino.test']);
        $ko = Cliente::factory()->create(['tenant_id' => $tenant->id, 'email' => 'ko@destino.test']);

        $this->loginAs($user);
        $this->configurarEmail();

        $this->post('/campanas', [
            'asunto' => 'Novedades',
            'cuerpo' => '<p>Hola</p>',
            'cliente_ids' => [$ok->id, $ko->id],
        ]);

        $campana = Campana::firstOrFail();
        $destOk = CampanaDestinatario::where('cliente_id', $ok->id)->firstOrFail();
        $destKo = CampanaDestinatario::where('cliente_id', $ko->id)->firstOrFail();

        Mail::shouldReceive('mailer')->andReturnSelf();
        Mail::shouldReceive('to')->andReturnSelf();
        Mail::shouldReceive('send')
            ->andReturnUsing(function ($mailable) {
                static $llamada = 0;
                $llamada++;
                if ($llamada === 2) {
                    throw new \RuntimeException('Buzón no disponible');
                }
            });

        $response = $this->postJson("/campanas/{$campana->id}/enviar-tanda", [
            'destinatario_ids' => [$destOk->id, $destKo->id],
        ]);

        $response->assertOk();

        $campana->refresh();
        $this->assertEquals(1, $campana->enviados);
        $this->assertEquals(1, $campana->fallidos);
        $this->assertEquals('finalizada', $campana->estado->value);
    }

    public function test_sin_smtp_configurado_enviar_tanda_devuelve_422(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $cliente = Cliente::factory()->create(['tenant_id' => $tenant->id, 'email' => 'uno@destino.test']);

        $campana = Campana::factory()->create(['tenant_id' => $tenant->id]);
        $destinatario = CampanaDestinatario::factory()->create([
            'tenant_id' => $tenant->id,
            'campana_id' => $campana->id,
            'cliente_id' => $cliente->id,
        ]);

        $this->loginAs($user);

        $this->postJson("/campanas/{$campana->id}/enviar-tanda", [
            'destinatario_ids' => [$destinatario->id],
        ])->assertStatus(422);
    }
}
