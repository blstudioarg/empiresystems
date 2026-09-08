<?php

namespace Tests\Feature\Asistente;

use App\Ia\ConversacionAsistente;
use App\Ia\Tools\CrearCliente;
use App\Models\LogActividad;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Flujo de escritura en dos fases (FR-007, SC-004): proponer NO escribe; solo el endpoint de
 * confirmación ejecuta.
 */
class ConfirmacionEscrituraTest extends TestCase
{
    use RefreshDatabase;

    private function activar(Tenant $tenant): void
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->getTenantKey());
    }

    public function test_proponer_no_escribe_en_bd(): void
    {
        $tenant = Tenant::factory()->create();
        $this->activar($tenant);
        tenancy()->initialize($tenant);

        (new CrearCliente)->proponer(['tipo' => 'particular', 'nombre' => 'Sin Guardar']);

        $this->assertDatabaseMissing('clientes', ['nombre' => 'Sin Guardar']);
    }

    public function test_confirmar_ejecuta_y_crea_el_registro(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->admin()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $this->loginAs($user);

        // Sembrar una acción pendiente en sesión.
        $conversacion = app(ConversacionAsistente::class);
        $id = $conversacion->proponerAccion('crear_cliente', ['tipo' => 'particular', 'nombre' => 'Textiles Sur'], 'Crear cliente Textiles Sur');

        $this->post("/asistente/accion/{$id}/confirmar")
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertDatabaseHas('clientes', ['nombre' => 'Textiles Sur', 'tenant_id' => $tenant->id]);
        $this->assertTrue(LogActividad::query()->where('tenant_id', $tenant->id)->where('descripcion', 'like', '%Asistente IA%')->exists());
    }

    public function test_cancelar_no_crea_y_limpia_la_pendiente(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->admin()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $this->loginAs($user);

        $conversacion = app(ConversacionAsistente::class);
        $id = $conversacion->proponerAccion('crear_cliente', ['tipo' => 'particular', 'nombre' => 'No Va'], 'Crear cliente No Va');

        $this->post("/asistente/accion/{$id}/cancelar")->assertOk();

        $this->assertDatabaseMissing('clientes', ['nombre' => 'No Va']);
    }

    public function test_id_inexistente_da_404(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->admin()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $this->loginAs($user);

        $this->post('/asistente/accion/no-existe/confirmar')->assertNotFound();
    }

    public function test_usuario_que_perdio_el_permiso_recibe_403(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $this->loginAs($user);

        // El usuario base no tiene ver-facturas... sí lo tiene. Quitamos todos los roles.
        $this->activar($tenant);
        $user->syncRoles([]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $conversacion = app(ConversacionAsistente::class);
        $id = $conversacion->proponerAccion('crear_cliente', ['tipo' => 'particular', 'nombre' => 'X'], 'Crear X');

        $this->post("/asistente/accion/{$id}/confirmar")->assertForbidden();
    }
}
