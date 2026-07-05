<?php

namespace Tests\Feature;

use App\Mail\EmailPrueba;
use App\Models\Configuracion;
use App\Models\Tenant;
use App\Models\User;
use App\Support\EmailTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ConfiguracionEmailTest extends TestCase
{
    use RefreshDatabase;

    private function datosValidos(array $overrides = []): array
    {
        return array_merge([
            'smtp_host' => 'smtp.hostinger.com',
            'smtp_port' => 465,
            'smtp_encryption' => 'ssl',
            'smtp_usuario' => 'facturas@empresa.test',
            'smtp_password' => 'secreto-smtp',
            'remitente' => 'facturas@empresa.test',
            'remitente_nombre' => 'Empresa Demo',
            'responder_a' => 'soporte@empresa.test',
        ], $overrides);
    }

    public function test_guardar_config_valida_persiste_las_8_claves_con_grupo_email(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $this->loginAs($user);

        $response = $this->put('/configuracion/email', $this->datosValidos());

        $response->assertRedirect(route('configuracion.show'));

        $this->assertEquals(
            8,
            Configuracion::query()->where('tenant_id', $tenant->id)->where('grupo', 'email')->count()
        );
    }

    public function test_password_queda_cifrada_en_bd_no_en_claro(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $this->loginAs($user);

        $this->put('/configuracion/email', $this->datosValidos(['smtp_password' => 'secreto-smtp']));

        $fila = Configuracion::query()
            ->where('tenant_id', $tenant->id)
            ->where('clave', EmailTenant::CLAVE_SMTP_PASSWORD)
            ->first();

        $this->assertNotNull($fila);
        $this->assertNotEquals('secreto-smtp', $fila->valor);
        $this->assertEquals('secreto-smtp', Crypt::decryptString($fila->valor));
    }

    public function test_guardar_sin_password_conserva_la_anterior(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $this->loginAs($user);

        $this->put('/configuracion/email', $this->datosValidos(['smtp_password' => 'password-original']));

        $this->put('/configuracion/email', $this->datosValidos(['smtp_password' => '']));

        $valores = EmailTenant::valores($tenant->id);
        $this->assertEquals('password-original', $valores['smtp_password']);
    }

    public function test_validacion_rechaza_remitente_no_email(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $this->loginAs($user);

        $response = $this->put('/configuracion/email', $this->datosValidos(['remitente' => 'no-es-un-email']));

        $response->assertSessionHasErrors('remitente');
    }

    public function test_validacion_rechaza_puerto_fuera_de_rango(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $this->loginAs($user);

        $response = $this->put('/configuracion/email', $this->datosValidos(['smtp_port' => 99999]));

        $response->assertSessionHasErrors('smtp_port');
    }

    public function test_enviar_prueba_con_credenciales_completas_envia_email(): void
    {
        Mail::fake();

        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $this->loginAs($user);

        $this->put('/configuracion/email', $this->datosValidos());

        $response = $this->post('/configuracion/email/prueba');

        $response->assertRedirect(route('configuracion.show'));
        $response->assertSessionHas('success');

        Mail::assertSent(EmailPrueba::class, function (EmailPrueba $mail) use ($user) {
            return $mail->hasTo($user->email);
        });
    }

    public function test_enviar_prueba_sin_config_completa_falla_controlado_sin_enviar(): void
    {
        Mail::fake();

        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $this->loginAs($user);

        $response = $this->post('/configuracion/email/prueba');

        $response->assertRedirect(route('configuracion.show'));
        $response->assertSessionHas('error');

        Mail::assertNothingSent();
    }

    public function test_configuracion_de_email_no_es_visible_desde_otro_tenant(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $userA = User::factory()->create(['tenant_id' => $tenantA->id, 'password' => bcrypt('secret123')]);
        $userB = User::factory()->create(['tenant_id' => $tenantB->id, 'password' => bcrypt('secret123')]);

        $this->loginAs($userA);
        $this->put('/configuracion/email', $this->datosValidos(['smtp_host' => 'smtp.tenant-a.test']));

        $this->assertFalse(EmailTenant::estaConfigurado($tenantB->id));

        $valoresB = EmailTenant::valores($tenantB->id);
        $this->assertEquals('', $valoresB['smtp_host']);

        $this->assertEquals(0, Configuracion::query()->where('tenant_id', $tenantB->id)->where('grupo', 'email')->count());
    }
}
