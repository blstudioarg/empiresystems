<?php

namespace Tests\Feature;

use App\Mail\FacturaMail;
use App\Models\Cliente;
use App\Models\Factura;
use App\Models\FacturaEvento;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class FacturaEnvioEmailTest extends TestCase
{
    use RefreshDatabase;

    private function configurarEmail(Tenant $tenant): void
    {
        $this->put('/configuracion/email', [
            'smtp_host' => 'smtp.hostinger.com',
            'smtp_port' => 465,
            'smtp_encryption' => 'ssl',
            'smtp_usuario' => 'facturas@empresa.test',
            'smtp_password' => 'secreto-smtp',
            'remitente' => 'facturas@empresa.test',
            'remitente_nombre' => 'Empresa Demo',
            'responder_a' => '',
        ]);
    }

    public function test_envio_de_factura_emitida_crea_mail_con_adjunto_y_registra_evento_ok(): void
    {
        Mail::fake();

        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $cliente = Cliente::factory()->create(['tenant_id' => $tenant->id, 'email' => 'cliente@destino.test']);
        $factura = Factura::factory()->emitida()->create(['tenant_id' => $tenant->id, 'cliente_id' => $cliente->id]);

        $this->loginAs($user);
        $this->configurarEmail($tenant);

        $response = $this->post("/facturas/{$factura->id}/enviar", ['destinatario' => 'cliente@destino.test']);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        Mail::assertSent(FacturaMail::class, function (FacturaMail $mail) use ($factura) {
            return $mail->hasTo('cliente@destino.test')
                && $mail->factura->id === $factura->id
                && count($mail->build()->rawAttachments) === 1;
        });

        $this->assertDatabaseHas('factura_eventos', [
            'tenant_id' => $tenant->id,
            'factura_id' => $factura->id,
            'tipo_evento' => 'envio_email',
        ]);

        $factura->refresh();
        $this->assertTrue($factura->fueEnviada());
    }

    public function test_tenant_sin_email_configurado_bloquea_el_envio(): void
    {
        Mail::fake();

        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $cliente = Cliente::factory()->create(['tenant_id' => $tenant->id, 'email' => 'cliente@destino.test']);
        $factura = Factura::factory()->emitida()->create(['tenant_id' => $tenant->id, 'cliente_id' => $cliente->id]);

        $this->loginAs($user);

        $response = $this->post("/facturas/{$factura->id}/enviar", ['destinatario' => 'cliente@destino.test']);

        $response->assertSessionHas('error');
        Mail::assertNothingSent();
        $this->assertDatabaseCount('factura_eventos', 0);
    }

    public function test_factura_en_borrador_no_se_puede_enviar(): void
    {
        Mail::fake();

        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $cliente = Cliente::factory()->create(['tenant_id' => $tenant->id, 'email' => 'cliente@destino.test']);
        $factura = Factura::factory()->create(['tenant_id' => $tenant->id, 'cliente_id' => $cliente->id, 'estado' => 'borrador']);

        $this->loginAs($user);
        $this->configurarEmail($tenant);

        $response = $this->post("/facturas/{$factura->id}/enviar", ['destinatario' => 'cliente@destino.test']);

        $response->assertSessionHas('error');
        Mail::assertNothingSent();
    }

    public function test_destinatario_invalido_o_vacio_falla_la_validacion(): void
    {
        Mail::fake();

        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $cliente = Cliente::factory()->create(['tenant_id' => $tenant->id, 'email' => null]);
        $factura = Factura::factory()->emitida()->create(['tenant_id' => $tenant->id, 'cliente_id' => $cliente->id]);

        $this->loginAs($user);
        $this->configurarEmail($tenant);

        $response = $this->post("/facturas/{$factura->id}/enviar", []);

        $response->assertSessionHasErrors('destinatario');
        Mail::assertNothingSent();
    }

    public function test_fallo_de_transporte_registra_evento_error_y_no_marca_enviada(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $cliente = Cliente::factory()->create(['tenant_id' => $tenant->id, 'email' => 'cliente@destino.test']);
        $factura = Factura::factory()->emitida()->create(['tenant_id' => $tenant->id, 'cliente_id' => $cliente->id]);

        $this->loginAs($user);
        $this->configurarEmail($tenant);

        Mail::shouldReceive('mailer')->once()->with('tenant_smtp')->andReturnSelf();
        Mail::shouldReceive('to')->once()->with('cliente@destino.test')->andReturnSelf();
        Mail::shouldReceive('send')->once()->andThrow(new \RuntimeException('Conexión rechazada por el servidor SMTP'));

        $response = $this->post("/facturas/{$factura->id}/enviar", ['destinatario' => 'cliente@destino.test']);

        $response->assertSessionHas('error');

        $this->assertDatabaseHas('factura_eventos', [
            'tenant_id' => $tenant->id,
            'factura_id' => $factura->id,
            'tipo_evento' => 'envio_email',
        ]);

        $factura->refresh();
        $this->assertFalse($factura->fueEnviada());
    }

    public function test_una_factura_de_otro_tenant_no_es_accesible(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $userA = User::factory()->create(['tenant_id' => $tenantA->id, 'password' => bcrypt('secret123')]);
        $clienteB = Cliente::factory()->create(['tenant_id' => $tenantB->id, 'email' => 'cliente@destino.test']);
        $facturaB = Factura::factory()->emitida()->create(['tenant_id' => $tenantB->id, 'cliente_id' => $clienteB->id]);

        $this->loginAs($userA);
        $this->configurarEmail($tenantA);

        $response = $this->post("/facturas/{$facturaB->id}/enviar", ['destinatario' => 'cliente@destino.test']);

        $response->assertNotFound();
    }

    public function test_reenvio_de_una_factura_ya_enviada_registra_un_segundo_evento(): void
    {
        Mail::fake();

        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $cliente = Cliente::factory()->create(['tenant_id' => $tenant->id, 'email' => 'cliente@destino.test']);
        $factura = Factura::factory()->emitida()->create(['tenant_id' => $tenant->id, 'cliente_id' => $cliente->id]);

        $this->loginAs($user);
        $this->configurarEmail($tenant);

        $this->post("/facturas/{$factura->id}/enviar", ['destinatario' => 'cliente@destino.test']);
        $this->post("/facturas/{$factura->id}/enviar", ['destinatario' => 'otro@destino.test']);

        $this->assertEquals(
            2,
            FacturaEvento::query()->where('factura_id', $factura->id)->where('tipo_evento', 'envio_email')->count()
        );
    }
}
