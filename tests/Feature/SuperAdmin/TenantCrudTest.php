<?php

namespace Tests\Feature\SuperAdmin;

use App\Enums\EstadoFactura;
use App\Models\Factura;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantCrudTest extends TestCase
{
    use RefreshDatabase;

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
        ]);

        $response->assertRedirect(route('super_admin.tenants.index'));

        $tenant = Tenant::where('nombre_comercial', 'Nuevo Tenant SL')->first();
        $this->assertNotNull($tenant);
        $this->assertEquals('nuevo-tenant.test', $tenant->dominio()?->domain);
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
