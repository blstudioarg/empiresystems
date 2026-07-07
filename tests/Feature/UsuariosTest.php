<?php

namespace Tests\Feature;

use App\Enums\EstadoUsuario;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UsuariosTest extends TestCase
{
    use RefreshDatabase;

    public function test_aprobar_pendiente_lo_habilita_para_iniciar_sesion(): void
    {
        $tenant = Tenant::factory()->create();
        $aprobador = User::factory()->admin()->create([
            'tenant_id' => $tenant->id,
            'password' => bcrypt('secret123'),
        ]);
        $pendiente = User::factory()->pendiente()->create([
            'tenant_id' => $tenant->id,
            'password' => bcrypt('secret123'),
        ]);

        $this->loginAs($aprobador);

        $response = $this->patch("/usuarios/{$pendiente->id}/aprobar");
        $response->assertRedirect();

        $pendiente->refresh();
        $this->assertSame(EstadoUsuario::Aprobado, $pendiente->estado);
        $this->assertTrue($pendiente->activo);
        $this->assertSame($aprobador->id, $pendiente->aprobado_por);
        $this->assertNotNull($pendiente->aprobado_en);

        $this->post('/logout');
        $login = $this->post('/login', [
            'email' => $pendiente->email,
            'password' => 'secret123',
        ]);
        $login->assertRedirect('/');
    }

    public function test_aprobar_dos_veces_es_idempotente(): void
    {
        $tenant = Tenant::factory()->create();
        $aprobador = User::factory()->admin()->create([
            'tenant_id' => $tenant->id,
            'password' => bcrypt('secret123'),
        ]);
        $pendiente = User::factory()->pendiente()->create(['tenant_id' => $tenant->id]);

        $this->loginAs($aprobador);

        $this->patch("/usuarios/{$pendiente->id}/aprobar");
        $this->patch("/usuarios/{$pendiente->id}/aprobar");

        $pendiente->refresh();
        $this->assertSame(EstadoUsuario::Aprobado, $pendiente->estado);
    }

    public function test_rechazar_bloquea_login(): void
    {
        $tenant = Tenant::factory()->create();
        $aprobador = User::factory()->admin()->create([
            'tenant_id' => $tenant->id,
            'password' => bcrypt('secret123'),
        ]);
        $pendiente = User::factory()->pendiente()->create([
            'tenant_id' => $tenant->id,
            'password' => bcrypt('secret123'),
        ]);

        $this->loginAs($aprobador);

        $this->patch("/usuarios/{$pendiente->id}/rechazar");

        $pendiente->refresh();
        $this->assertSame(EstadoUsuario::Rechazado, $pendiente->estado);
        $this->assertFalse($pendiente->activo);

        $this->post('/logout');
        $login = $this->from('/login')->post('/login', [
            'email' => $pendiente->email,
            'password' => 'secret123',
        ]);
        $login->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_usuario_no_puede_aprobarse_ni_rechazarse_a_si_mismo(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->admin()->create([
            'tenant_id' => $tenant->id,
            'password' => bcrypt('secret123'),
        ]);

        $this->loginAs($user);

        $this->patch("/usuarios/{$user->id}/aprobar")->assertStatus(403);
        $this->patch("/usuarios/{$user->id}/rechazar")->assertStatus(403);
    }

    public function test_listado_de_usuarios_esta_aislado_por_tenant(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $userA = User::factory()->admin()->create([
            'tenant_id' => $tenantA->id,
            'password' => bcrypt('secret123'),
        ]);
        $userB = User::factory()->create([
            'tenant_id' => $tenantB->id,
            'name' => 'Usuario De Otro Tenant',
        ]);

        $this->loginAs($userA);

        $response = $this->get('/usuarios');
        $response->assertOk();
        $response->assertDontSee('Usuario De Otro Tenant');
    }

    public function test_no_puede_aprobar_ni_rechazar_usuario_de_otro_tenant(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $userA = User::factory()->admin()->create([
            'tenant_id' => $tenantA->id,
            'password' => bcrypt('secret123'),
        ]);
        $pendienteB = User::factory()->pendiente()->create(['tenant_id' => $tenantB->id]);

        $this->loginAs($userA);

        $this->patch("/usuarios/{$pendienteB->id}/aprobar")->assertStatus(404);
        $this->patch("/usuarios/{$pendienteB->id}/rechazar")->assertStatus(404);
    }

    public function test_totales_reflejan_conteos_correctos_del_tenant(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $userA = User::factory()->admin()->create([
            'tenant_id' => $tenantA->id,
            'password' => bcrypt('secret123'),
        ]);
        User::factory()->pendiente()->create(['tenant_id' => $tenantA->id]);
        User::factory()->pendiente()->create(['tenant_id' => $tenantA->id]);
        User::factory()->create(['tenant_id' => $tenantA->id]);

        User::factory()->pendiente()->create(['tenant_id' => $tenantB->id]);

        $this->loginAs($userA);

        $response = $this->getJson('/usuarios');
        $response->assertOk();
        $response->assertJson([
            'totales' => [
                'total' => 4,
                'pendientes' => 2,
                'activos' => 2,
            ],
        ]);
    }
}
