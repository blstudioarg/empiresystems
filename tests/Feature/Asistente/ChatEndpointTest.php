<?php

namespace Tests\Feature\Asistente;

use App\Models\Tenant;
use App\Models\User;
use App\Support\IaTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_mensaje_exige_autenticacion(): void
    {
        $tenant = Tenant::factory()->create();
        $host = $this->domainFor($tenant);

        $this->actingOnDomain($host)
            ->post('/asistente/mensaje', ['mensaje' => 'hola'])
            ->assertRedirect(); // guest → redirige a login
    }

    public function test_sin_clave_devuelve_409_no_configurado(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->admin()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $this->loginAs($user);

        $this->post('/asistente/mensaje', ['mensaje' => 'hola'])
            ->assertStatus(409)
            ->assertJson(['error' => 'no_configurado']);
    }

    public function test_mensaje_requiere_texto(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->admin()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        IaTenant::guardarApiKey('sk-ant-x', $tenant->id);
        $this->loginAs($user);

        $this->postJson('/asistente/mensaje', ['mensaje' => ''])->assertStatus(422);
    }

    /**
     * La ruta `asistente/reiniciar` desapareció en la feature 045: la sustituye la creación de
     * conversación, que además ya no descarta el hilo anterior sino que lo deja en el historial.
     */
    public function test_conversacion_nueva_deja_el_panel_vacio(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->admin()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $this->loginAs($user);

        $this->post('/asistente/conversaciones')->assertOk()->assertJson(['ok' => true]);
    }

    public function test_widget_visible_para_admin_sin_clave_en_modo_activar(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->admin()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $this->loginAs($user);

        $response = $this->get('/');
        $response->assertSee('asistente-chat', false);
        $response->assertSee('Ir a Configuración');
    }

    public function test_widget_no_existe_para_usuario_base_sin_clave(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]); // sin ver-configuracion
        $this->loginAs($user);

        $this->get('/')->assertDontSee('id="asistente-chat"', false);
    }

    public function test_widget_visible_para_todos_con_clave(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        IaTenant::guardarApiKey('sk-ant-x', $tenant->id);
        $this->loginAs($user);

        $this->get('/')->assertSee('id="asistente-chat"', false);
    }
}
