<?php

namespace Tests\Feature;

use App\Models\Campana;
use App\Models\CampanaDestinatario;
use App\Models\Cliente;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class CampanaAislamientoTenantTest extends TestCase
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

    public function test_el_listado_de_un_tenant_no_incluye_campanas_de_otro(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $userA = User::factory()->create(['tenant_id' => $tenantA->id, 'password' => bcrypt('secret123')]);

        Campana::factory()->create(['tenant_id' => $tenantA->id, 'asunto' => 'Campaña de A']);
        Campana::factory()->create(['tenant_id' => $tenantB->id, 'asunto' => 'Campaña de B']);

        $this->loginAs($userA);

        $response = $this->getJson('/campanas');

        $response->assertOk();
        $response->assertJsonFragment(['asunto' => 'Campaña de A']);
        $response->assertJsonMissing(['asunto' => 'Campaña de B']);
    }

    public function test_no_se_puede_ver_una_campana_de_otro_tenant(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $userA = User::factory()->create(['tenant_id' => $tenantA->id, 'password' => bcrypt('secret123')]);

        $campanaB = Campana::factory()->create(['tenant_id' => $tenantB->id]);

        $this->loginAs($userA);

        $this->get("/campanas/{$campanaB->id}")->assertNotFound();
    }

    public function test_no_se_puede_crear_una_campana_con_un_cliente_de_otro_tenant(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $userA = User::factory()->create(['tenant_id' => $tenantA->id, 'password' => bcrypt('secret123')]);

        $clienteB = Cliente::factory()->create(['tenant_id' => $tenantB->id]);

        $this->loginAs($userA);
        $this->configurarEmail();

        $response = $this->post('/campanas', [
            'asunto' => 'Hola',
            'cuerpo' => '<p>Contenido</p>',
            'cliente_ids' => [$clienteB->id],
        ]);

        $response->assertSessionHasErrors('cliente_ids.0');
        $this->assertDatabaseCount('campanas', 0);
    }

    public function test_no_se_puede_enviar_tanda_a_destinatarios_de_otro_tenant(): void
    {
        Mail::fake();

        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $userA = User::factory()->create(['tenant_id' => $tenantA->id, 'password' => bcrypt('secret123')]);

        $campanaB = Campana::factory()->create(['tenant_id' => $tenantB->id]);
        $clienteB = Cliente::factory()->create(['tenant_id' => $tenantB->id]);
        $destinatarioB = CampanaDestinatario::factory()->create([
            'tenant_id' => $tenantB->id,
            'campana_id' => $campanaB->id,
            'cliente_id' => $clienteB->id,
        ]);

        $this->loginAs($userA);
        $this->configurarEmail();

        $this->post("/campanas/{$campanaB->id}/enviar-tanda", [
            'destinatario_ids' => [$destinatarioB->id],
        ])->assertNotFound();

        Mail::assertNothingSent();
    }
}
