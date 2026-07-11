<?php

namespace Tests\Feature\SuperAdmin;

use App\Enums\EstadoFactura;
use App\Enums\EstadoUsuario;
use App\Enums\UserRole;
use App\Models\Factura;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\PermisosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // El alta de tenant provisiona los roles Administrador/Usuario (feature 027) contra el
        // catálogo global de permisos: debe existir antes, tal como en producción (migrate + seed).
        $this->seed(PermisosSeeder::class);
    }

    public function test_guest_es_redirigido_a_login(): void
    {
        $response = $this->get('http://localhost/super_admin/tenants');

        $response->assertRedirect(route('login'));
    }

    public function test_usuario_de_tenant_no_puede_acceder(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);

        $this->post("http://{$this->domainFor($tenant)}/login", [
            'email' => $user->email,
            'password' => 'secret123',
        ]);

        $response = $this->get('http://localhost/super_admin/tenants');

        $response->assertForbidden();
    }

    public function test_super_admin_ve_el_listado(): void
    {
        Tenant::factory()->count(2)->create();

        $superAdmin = User::factory()->superAdmin()->create(['password' => bcrypt('secret123')]);

        $this->post('http://localhost/login', [
            'email' => $superAdmin->email,
            'password' => 'secret123',
        ]);

        $response = $this->get('http://localhost/super_admin/tenants');

        $response->assertOk();
    }

    private function actingAsSuperAdmin(): User
    {
        $superAdmin = User::factory()->superAdmin()->create(['password' => bcrypt('secret123')]);

        $this->post('http://localhost/login', [
            'email' => $superAdmin->email,
            'password' => 'secret123',
        ]);

        return $superAdmin;
    }

    public function test_alta_crea_tenant_y_dominio(): void
    {
        $this->actingAsSuperAdmin();

        $response = $this->post('http://localhost/super_admin/tenants', [
            'dominio' => 'nuevo-tenant.test',
            'nombre_comercial' => 'Nuevo Tenant SL',
            'razon_social' => 'Nuevo Tenant Sociedad Limitada',
            'nif' => 'B12345674',
            'regimen_impositivo' => 'iva',
            'email' => 'contacto@nuevo-tenant.test',
            'admin_email' => 'admin@nuevo-tenant.test',
            'admin_password' => 'password123',
        ]);

        $response->assertRedirect(route('super_admin.tenants.index'));

        $tenant = Tenant::where('nombre_comercial', 'Nuevo Tenant SL')->first();
        $this->assertNotNull($tenant);
        $this->assertEquals('nuevo-tenant.test', $tenant->dominio()?->domain);
    }

    public function test_alta_crea_usuario_administrador_activo_y_aprobado(): void
    {
        $this->actingAsSuperAdmin();

        $this->post('http://localhost/super_admin/tenants', [
            'dominio' => 'nuevo-tenant.test',
            'nombre_comercial' => 'Nuevo Tenant SL',
            'razon_social' => 'Nuevo Tenant Sociedad Limitada',
            'nif' => 'B12345674',
            'regimen_impositivo' => 'iva',
            'email' => 'contacto@nuevo-tenant.test',
            'admin_email' => 'admin@nuevo-tenant.test',
            'admin_password' => 'password123',
        ]);

        $tenant = Tenant::where('nombre_comercial', 'Nuevo Tenant SL')->first();
        $admin = User::where('tenant_id', $tenant->id)->where('email', 'admin@nuevo-tenant.test')->first();

        $this->assertNotNull($admin);
        $this->assertEquals(UserRole::Admin, $admin->rol);
        $this->assertEquals(EstadoUsuario::Aprobado, $admin->estado);
        $this->assertTrue($admin->activo);
    }

    public function test_administrador_creado_puede_iniciar_sesion_en_el_dominio_del_tenant(): void
    {
        $this->actingAsSuperAdmin();

        $this->post('http://localhost/super_admin/tenants', [
            'dominio' => 'nuevo-tenant.test',
            'nombre_comercial' => 'Nuevo Tenant SL',
            'razon_social' => 'Nuevo Tenant Sociedad Limitada',
            'nif' => 'B12345674',
            'regimen_impositivo' => 'iva',
            'email' => 'contacto@nuevo-tenant.test',
            'admin_email' => 'admin@nuevo-tenant.test',
            'admin_password' => 'password123',
        ]);

        $this->post('http://localhost/logout');

        $tenant = Tenant::where('nombre_comercial', 'Nuevo Tenant SL')->first();
        $admin = User::where('tenant_id', $tenant->id)->where('email', 'admin@nuevo-tenant.test')->first();

        $response = $this->post("http://{$this->domainFor($tenant)}/login", [
            'email' => 'admin@nuevo-tenant.test',
            'password' => 'password123',
        ]);

        $response->assertRedirect('/');
        $this->assertAuthenticatedAs($admin);
    }

    public function test_administrador_de_un_tenant_no_es_visible_ni_autenticable_en_otro_tenant(): void
    {
        $this->actingAsSuperAdmin();

        $this->post('http://localhost/super_admin/tenants', [
            'dominio' => 'tenant-a.test',
            'nombre_comercial' => 'Tenant A SL',
            'razon_social' => 'Tenant A Sociedad Limitada',
            'nif' => 'B12345674',
            'regimen_impositivo' => 'iva',
            'email' => 'contacto@tenant-a.test',
            'admin_email' => 'admin@tenant-a.test',
            'admin_password' => 'password123',
        ]);

        $this->post('http://localhost/super_admin/tenants', [
            'dominio' => 'tenant-b.test',
            'nombre_comercial' => 'Tenant B SL',
            'razon_social' => 'Tenant B Sociedad Limitada',
            'nif' => 'B87654323',
            'regimen_impositivo' => 'iva',
            'email' => 'contacto@tenant-b.test',
            'admin_email' => 'admin@tenant-b.test',
            'admin_password' => 'password123',
        ]);

        $tenantA = Tenant::where('nombre_comercial', 'Tenant A SL')->first();
        $tenantB = Tenant::where('nombre_comercial', 'Tenant B SL')->first();

        $this->assertNull(
            User::where('tenant_id', $tenantB->id)->where('email', 'admin@tenant-a.test')->first()
        );

        $this->post('http://localhost/logout');

        $response = $this->post("http://{$this->domainFor($tenantB)}/login", [
            'email' => 'admin@tenant-a.test',
            'password' => 'password123',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_alta_provisiona_rol_administrador_con_todo_el_catalogo(): void
    {
        $this->actingAsSuperAdmin();

        $this->post('http://localhost/super_admin/tenants', [
            'dominio' => 'nuevo-tenant.test',
            'nombre_comercial' => 'Nuevo Tenant SL',
            'razon_social' => 'Nuevo Tenant Sociedad Limitada',
            'nif' => 'B12345674',
            'regimen_impositivo' => 'iva',
            'email' => 'contacto@nuevo-tenant.test',
            'admin_email' => 'admin@nuevo-tenant.test',
            'admin_password' => 'password123',
        ]);

        $tenant = Tenant::where('nombre_comercial', 'Nuevo Tenant SL')->first();
        $admin = User::where('tenant_id', $tenant->id)->where('email', 'admin@nuevo-tenant.test')->first();

        $registrar = app(\Spatie\Permission\PermissionRegistrar::class);
        $registrar->setPermissionsTeamId($tenant->getTenantKey());

        $rolAdmin = \Spatie\Permission\Models\Role::where('tenant_id', $tenant->getTenantKey())
            ->where('name', \App\Support\ProvisionadorRoles::ROL_ADMINISTRADOR)->first();

        $this->assertNotNull($rolAdmin);
        $this->assertCount(21, $rolAdmin->permissions);
        $this->assertTrue($admin->fresh()->hasRole($rolAdmin));

        $rolUsuario = \Spatie\Permission\Models\Role::where('tenant_id', $tenant->getTenantKey())
            ->where('name', \App\Support\ProvisionadorRoles::ROL_USUARIO)->first();
        $this->assertNotNull($rolUsuario);
        $this->assertTrue((bool) $rolUsuario->es_defecto);
    }

    public function test_fallo_al_provisionar_roles_no_deja_tenant_parcial(): void
    {
        $this->actingAsSuperAdmin();

        \Spatie\Permission\Models\Role::creating(function () {
            throw new \RuntimeException('Fallo simulado al provisionar roles.');
        });

        try {
            $this->withoutExceptionHandling()->post('http://localhost/super_admin/tenants', [
                'dominio' => 'falla-roles.test',
                'nombre_comercial' => 'Falla Roles SL',
                'razon_social' => 'Falla Roles Sociedad Limitada',
                'nif' => 'B12345674',
                'regimen_impositivo' => 'iva',
                'email' => 'contacto@falla-roles.test',
                'admin_email' => 'admin@falla-roles.test',
                'admin_password' => 'password123',
            ]);

            $this->fail('Se esperaba que el fallo de provisión lanzara una excepción.');
        } catch (\RuntimeException $e) {
            $this->assertSame('Fallo simulado al provisionar roles.', $e->getMessage());
        }

        $this->assertDatabaseMissing('tenants', ['nombre_comercial' => 'Falla Roles SL']);
        $this->assertDatabaseMissing('domains', ['domain' => 'falla-roles.test']);
        $this->assertDatabaseMissing('users', ['email' => 'admin@falla-roles.test']);
    }

    public function test_fallo_al_crear_administrador_revierte_tenant_y_dominio(): void
    {
        $this->actingAsSuperAdmin();

        User::creating(function () {
            throw new \RuntimeException('Fallo simulado al crear el administrador.');
        });

        try {
            $this->withoutExceptionHandling()->post('http://localhost/super_admin/tenants', [
                'dominio' => 'falla-tenant.test',
                'nombre_comercial' => 'Falla Tenant SL',
                'razon_social' => 'Falla Tenant Sociedad Limitada',
                'nif' => 'B12345674',
                'regimen_impositivo' => 'iva',
                'email' => 'contacto@falla-tenant.test',
                'admin_email' => 'admin@falla-tenant.test',
                'admin_password' => 'password123',
            ]);

            $this->fail('Se esperaba que la creación del administrador lanzara una excepción.');
        } catch (\RuntimeException $e) {
            $this->assertSame('Fallo simulado al crear el administrador.', $e->getMessage());
        }

        $this->assertDatabaseMissing('tenants', ['nombre_comercial' => 'Falla Tenant SL']);
        $this->assertDatabaseMissing('domains', ['domain' => 'falla-tenant.test']);
    }

    public function test_alta_sin_credenciales_de_administrador_falla_y_no_crea_tenant(): void
    {
        $this->actingAsSuperAdmin();

        $response = $this->post('http://localhost/super_admin/tenants', [
            'dominio' => 'sin-admin.test',
            'nombre_comercial' => 'Sin Admin SL',
            'razon_social' => 'Sin Admin Sociedad Limitada',
            'nif' => 'B12345674',
            'regimen_impositivo' => 'iva',
            'email' => 'contacto@sin-admin.test',
            'admin_email' => '',
            'admin_password' => '',
        ]);

        $response->assertSessionHasErrors(['admin_email', 'admin_password']);
        $this->assertNull(Tenant::where('nombre_comercial', 'Sin Admin SL')->first());
    }

    public function test_alta_con_email_de_administrador_invalido_falla(): void
    {
        $this->actingAsSuperAdmin();

        $response = $this->post('http://localhost/super_admin/tenants', [
            'dominio' => 'email-invalido.test',
            'nombre_comercial' => 'Email Invalido SL',
            'razon_social' => 'Email Invalido Sociedad Limitada',
            'nif' => 'B12345674',
            'regimen_impositivo' => 'iva',
            'email' => 'contacto@email-invalido.test',
            'admin_email' => 'no-es-un-email',
            'admin_password' => 'password123',
        ]);

        $response->assertSessionHasErrors('admin_email');
        $this->assertNull(Tenant::where('nombre_comercial', 'Email Invalido SL')->first());
    }

    public function test_alta_con_contrasena_de_administrador_demasiado_corta_falla(): void
    {
        $this->actingAsSuperAdmin();

        $response = $this->post('http://localhost/super_admin/tenants', [
            'dominio' => 'password-corta.test',
            'nombre_comercial' => 'Password Corta SL',
            'razon_social' => 'Password Corta Sociedad Limitada',
            'nif' => 'B12345674',
            'regimen_impositivo' => 'iva',
            'email' => 'contacto@password-corta.test',
            'admin_email' => 'admin@password-corta.test',
            'admin_password' => 'corta',
        ]);

        $response->assertSessionHasErrors('admin_password');
        $this->assertNull(Tenant::where('nombre_comercial', 'Password Corta SL')->first());
    }

    public function test_alta_con_dominio_duplicado_falla(): void
    {
        $existente = Tenant::factory()->create();
        $dominioExistente = $this->domainFor($existente);

        $this->actingAsSuperAdmin();

        $response = $this->post('http://localhost/super_admin/tenants', [
            'dominio' => $dominioExistente,
            'nombre_comercial' => 'Otro Tenant',
            'razon_social' => 'Otro Tenant SL',
            'nif' => 'B87654323',
            'regimen_impositivo' => 'iva',
            'email' => 'otro@example.com',
        ]);

        $response->assertSessionHasErrors('dominio');
    }

    public function test_dominio_normalizado_se_trata_como_el_mismo(): void
    {
        $existente = Tenant::factory()->create();
        $dominioExistente = $this->domainFor($existente);

        $this->actingAsSuperAdmin();

        $response = $this->post('http://localhost/super_admin/tenants', [
            'dominio' => 'HTTPS://'.strtoupper($dominioExistente).'/',
            'nombre_comercial' => 'Otro Tenant',
            'razon_social' => 'Otro Tenant SL',
            'nif' => 'B87654323',
            'regimen_impositivo' => 'iva',
            'email' => 'otro@example.com',
        ]);

        $response->assertSessionHasErrors('dominio');
    }

    public function test_edicion_de_dominio_a_uno_libre_funciona_y_el_anterior_deja_de_resolver(): void
    {
        $tenant = Tenant::factory()->create();
        $dominioAnterior = $this->domainFor($tenant);

        $this->actingAsSuperAdmin();

        $response = $this->put("http://localhost/super_admin/tenants/{$tenant->id}", [
            'dominio' => 'nuevo-dominio.test',
            'nombre_comercial' => $tenant->nombre_comercial,
            'razon_social' => $tenant->razon_social,
            'nif' => 'B12345674',
            'regimen_impositivo' => $tenant->regimen_impositivo->value,
            'email' => $tenant->email,
            'activo' => '1',
        ]);

        $response->assertRedirect(route('super_admin.tenants.index'));
        $this->assertEquals('nuevo-dominio.test', $tenant->fresh()->dominio()?->domain);

        $respuestaViejoDominio = $this->get("http://{$dominioAnterior}/");
        $respuestaViejoDominio->assertNotFound();
    }

    public function test_edicion_con_dominio_de_otro_tenant_falla(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $dominioB = $this->domainFor($tenantB);

        $this->actingAsSuperAdmin();

        $response = $this->put("http://localhost/super_admin/tenants/{$tenantA->id}", [
            'dominio' => $dominioB,
            'nombre_comercial' => $tenantA->nombre_comercial,
            'razon_social' => $tenantA->razon_social,
            'nif' => $tenantA->nif,
            'regimen_impositivo' => $tenantA->regimen_impositivo->value,
            'email' => $tenantA->email,
            'activo' => '1',
        ]);

        $response->assertSessionHasErrors('dominio');
    }

    public function test_desactivar_tenant_bloquea_login_de_sus_usuarios(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $dominio = $this->domainFor($tenant);

        $this->actingAsSuperAdmin();

        $this->put("http://localhost/super_admin/tenants/{$tenant->id}", [
            'dominio' => $dominio,
            'nombre_comercial' => $tenant->nombre_comercial,
            'razon_social' => $tenant->razon_social,
            'nif' => 'B12345674',
            'regimen_impositivo' => $tenant->regimen_impositivo->value,
            'email' => $tenant->email,
            'activo' => '0',
        ]);

        $this->post('http://localhost/logout');

        $response = $this->post("http://{$dominio}/login", [
            'email' => $user->email,
            'password' => 'secret123',
        ]);

        $response->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_destroy_bloqueado_si_tiene_facturas_emitidas(): void
    {
        $tenant = Tenant::factory()->create();
        Factura::factory()->create(['tenant_id' => $tenant->id, 'estado' => EstadoFactura::Emitida]);

        $this->actingAsSuperAdmin();

        $response = $this->delete("http://localhost/super_admin/tenants/{$tenant->id}");

        $response->assertRedirect(route('super_admin.tenants.index'));
        $response->assertSessionHas('error');

        $this->assertNotNull($tenant->fresh());
        $this->assertDatabaseHas('facturas', ['tenant_id' => $tenant->id]);
    }

    public function test_destroy_sin_facturas_elimina_tenant_y_su_dominio(): void
    {
        $tenant = Tenant::factory()->create();
        $dominio = $this->domainFor($tenant);

        $this->actingAsSuperAdmin();

        $response = $this->delete("http://localhost/super_admin/tenants/{$tenant->id}");

        $response->assertRedirect(route('super_admin.tenants.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseMissing('tenants', ['id' => $tenant->id]);
        $this->assertDatabaseMissing('domains', ['domain' => $dominio]);
    }
}
