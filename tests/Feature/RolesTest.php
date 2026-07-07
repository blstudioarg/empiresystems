<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Support\CatalogoPermisos;
use App\Support\ProvisionadorRoles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\GestionaRolesDeTenant;
use Tests\TestCase;

class RolesTest extends TestCase
{
    use GestionaRolesDeTenant, RefreshDatabase;

    public function test_ruta_roles_exige_ver_roles(): void
    {
        $this->sembrarPermisos();
        $tenant = Tenant::factory()->create();
        $usuario = $this->usuarioConRol($tenant, $this->crearRol($tenant, 'Ventas', ['ver-clientes']));

        $this->loginAs($usuario);

        $this->get('/roles')->assertForbidden();
    }

    public function test_index_json_lista_solo_roles_del_tenant_activo_con_catalogo_y_totales(): void
    {
        $this->sembrarPermisos();
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $rolAdmin = $this->crearRol($tenantA, 'Administrador', CatalogoPermisos::claves());
        $this->crearRol($tenantA, 'Ventas', ['ver-clientes']);
        $this->crearRol($tenantB, 'Rol de B', ['ver-facturas']);

        $usuario = $this->usuarioConRol($tenantA, $rolAdmin);

        $this->loginAs($usuario);

        $response = $this->getJson('/roles');

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
        $response->assertJsonFragment(['name' => 'Ventas']);
        $response->assertJsonMissing(['name' => 'Rol de B']);

        $data = $response->json();
        $this->assertSame(2, $data['totales']['roles']);
        $this->assertSame(17, $data['totales']['permisos_catalogo']);
        $this->assertCount(count(CatalogoPermisos::porModulo()), $data['catalogo']);

        $admin = collect($data['data'])->firstWhere('name', 'Administrador');
        $this->assertTrue($admin['es_administrador']);
        $this->assertSame(17, $admin['num_permisos']);
    }

    public function test_store_valida_nombre_requerido_y_unico_por_tenant_pero_permite_repetir_en_otro(): void
    {
        $this->sembrarPermisos();
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $rolAdmin = $this->crearRol($tenantA, 'Administrador', CatalogoPermisos::claves());
        $usuario = $this->usuarioConRol($tenantA, $rolAdmin);
        $this->crearRol($tenantB, 'Ventas', ['ver-clientes']);

        $this->loginAs($usuario);

        $this->postJson('/roles', ['permisos' => ['ver-clientes']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');

        // Mismo nombre en otro tenant: permitido (RN-05).
        $this->postJson('/roles', ['name' => 'Ventas', 'permisos' => ['ver-clientes']])
            ->assertCreated();

        // Repetirlo en el mismo tenant: rechazado.
        $this->postJson('/roles', ['name' => 'Ventas', 'permisos' => ['ver-facturas']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');
    }

    public function test_store_valida_permisos_existentes_en_el_catalogo(): void
    {
        $this->sembrarPermisos();
        $tenant = Tenant::factory()->create();
        $usuario = $this->usuarioConRol($tenant, $this->crearRol($tenant, 'Administrador', CatalogoPermisos::claves()));

        $this->loginAs($usuario);

        $this->postJson('/roles', ['name' => 'Ventas', 'permisos' => ['no-existe']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('permisos.0');

        $this->postJson('/roles', ['name' => 'Ventas', 'permisos' => []])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('permisos');
    }

    public function test_update_de_rol_de_otro_tenant_da_404(): void
    {
        $this->sembrarPermisos();
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $rolB = $this->crearRol($tenantB, 'Ventas', ['ver-clientes']);
        $usuario = $this->usuarioConRol($tenantA, $this->crearRol($tenantA, 'Administrador', CatalogoPermisos::claves()));

        $this->loginAs($usuario);

        $this->putJson("/roles/{$rolB->id}", ['name' => 'Ventas Editado', 'permisos' => ['ver-clientes']])
            ->assertNotFound();
    }

    public function test_editar_rol_actualiza_los_permisos_efectivos_en_el_siguiente_request(): void
    {
        $this->sembrarPermisos();
        $tenant = Tenant::factory()->create();
        $rolAdmin = $this->crearRol($tenant, 'Administrador', CatalogoPermisos::claves());
        $rolVentas = $this->crearRol($tenant, 'Ventas', ['ver-clientes']);
        $vendedor = $this->usuarioConRol($tenant, $rolVentas);
        $admin = $this->usuarioConRol($tenant, $rolAdmin);

        $this->loginAs($vendedor);
        $this->get('/facturas')->assertForbidden();
        $this->post('/logout');

        $this->loginAs($admin);
        $this->putJson("/roles/{$rolVentas->id}", [
            'name' => 'Ventas',
            'permisos' => ['ver-clientes', 'ver-facturas'],
        ])->assertOk();
        $this->post('/logout');

        $this->loginAs($vendedor);
        $this->get('/facturas')->assertOk();
    }

    public function test_destroy_con_usuarios_asignados_da_409(): void
    {
        $this->sembrarPermisos();
        $tenant = Tenant::factory()->create();
        $rolAdmin = $this->crearRol($tenant, 'Administrador', CatalogoPermisos::claves());
        $rolVentas = $this->crearRol($tenant, 'Ventas', ['ver-clientes']);
        $this->usuarioConRol($tenant, $rolVentas);
        $admin = $this->usuarioConRol($tenant, $rolAdmin);

        $this->loginAs($admin);

        $this->deleteJson("/roles/{$rolVentas->id}")->assertStatus(409);
    }

    public function test_destroy_sin_usuarios_elimina_el_rol(): void
    {
        $this->sembrarPermisos();
        $tenant = Tenant::factory()->create();
        $rolAdmin = $this->crearRol($tenant, 'Administrador', CatalogoPermisos::claves());
        $rolVentas = $this->crearRol($tenant, 'Ventas', ['ver-clientes']);
        $admin = $this->usuarioConRol($tenant, $rolAdmin);

        $this->loginAs($admin);

        $this->deleteJson("/roles/{$rolVentas->id}")->assertOk();
    }

    public function test_no_se_puede_renombrar_el_rol_administrador(): void
    {
        $this->sembrarPermisos();
        $tenant = Tenant::factory()->create();
        $rolAdmin = $this->crearRol($tenant, 'Administrador', CatalogoPermisos::claves());
        $admin = $this->usuarioConRol($tenant, $rolAdmin);

        $this->loginAs($admin);

        $this->putJson("/roles/{$rolAdmin->id}", [
            'name' => 'Super Administrador',
            'permisos' => CatalogoPermisos::claves(),
        ])->assertStatus(422);
    }

    public function test_no_se_le_puede_quitar_ver_roles_o_ver_usuarios_al_rol_administrador(): void
    {
        $this->sembrarPermisos();
        $tenant = Tenant::factory()->create();
        $rolAdmin = $this->crearRol($tenant, 'Administrador', CatalogoPermisos::claves());
        $admin = $this->usuarioConRol($tenant, $rolAdmin);

        $this->loginAs($admin);

        $sinVerRoles = array_values(array_diff(CatalogoPermisos::claves(), ['ver-roles']));
        $this->putJson("/roles/{$rolAdmin->id}", ['name' => 'Administrador', 'permisos' => $sinVerRoles])
            ->assertStatus(422);

        $sinVerUsuarios = array_values(array_diff(CatalogoPermisos::claves(), ['ver-usuarios']));
        $this->putJson("/roles/{$rolAdmin->id}", ['name' => 'Administrador', 'permisos' => $sinVerUsuarios])
            ->assertStatus(422);
    }

    public function test_el_rol_administrador_no_se_puede_eliminar(): void
    {
        $this->sembrarPermisos();
        $tenant = Tenant::factory()->create();
        $rolAdmin = $this->crearRol($tenant, 'Administrador', CatalogoPermisos::claves());
        $admin = $this->usuarioConRol($tenant, $rolAdmin);

        $this->loginAs($admin);

        $this->deleteJson("/roles/{$rolAdmin->id}")->assertStatus(409);
    }

    public function test_marcar_rol_por_defecto_desmarca_el_anterior(): void
    {
        $this->sembrarPermisos();
        $tenant = Tenant::factory()->create();
        $rolAdmin = $this->crearRol($tenant, 'Administrador', CatalogoPermisos::claves());
        $rolUsuario = $this->crearRol($tenant, ProvisionadorRoles::ROL_USUARIO, CatalogoPermisos::clavesUsuarioBase(), esDefecto: true);
        $rolVentas = $this->crearRol($tenant, 'Ventas', ['ver-clientes']);
        $admin = $this->usuarioConRol($tenant, $rolAdmin);

        $this->loginAs($admin);

        $this->patchJson("/roles/{$rolVentas->id}/defecto")->assertOk();

        $this->assertTrue((bool) $rolVentas->fresh()->es_defecto);
        $this->assertFalse((bool) $rolUsuario->fresh()->es_defecto);
    }

    public function test_destroy_del_rol_por_defecto_da_409(): void
    {
        $this->sembrarPermisos();
        $tenant = Tenant::factory()->create();
        $rolAdmin = $this->crearRol($tenant, 'Administrador', CatalogoPermisos::claves());
        $rolUsuario = $this->crearRol($tenant, ProvisionadorRoles::ROL_USUARIO, CatalogoPermisos::clavesUsuarioBase(), esDefecto: true);
        $admin = $this->usuarioConRol($tenant, $rolAdmin);

        $this->loginAs($admin);

        $this->deleteJson("/roles/{$rolUsuario->id}")->assertStatus(409);
    }
}
