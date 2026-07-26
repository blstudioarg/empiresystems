<?php

namespace Tests\Feature;

use App\Models\CanalCaptacion;
use App\Models\Lead;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\GestionaRolesDeTenant;
use Tests\TestCase;

class CanalCaptacionCrudTest extends TestCase
{
    use GestionaRolesDeTenant, RefreshDatabase;

    private function usuarioConfiguracion(Tenant $tenant)
    {
        $this->sembrarPermisos();
        $rol = $this->crearRol($tenant, 'Administrador', \App\Support\CatalogoPermisos::claves());

        return $this->usuarioConRol($tenant, $rol);
    }

    public function test_nombre_unico_por_tenant(): void
    {
        $tenant = Tenant::factory()->create();
        $usuario = $this->usuarioConfiguracion($tenant);
        $this->loginAs($usuario);

        $this->postJson('/canales-captacion', ['nombre' => 'Ferias'])->assertCreated();
        $this->postJson('/canales-captacion', ['nombre' => 'Ferias'])->assertStatus(422);
    }

    public function test_mismo_nombre_permitido_en_otro_tenant(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        tenancy()->initialize($tenantA);
        CanalCaptacion::factory()->create(['tenant_id' => $tenantA->id, 'nombre' => 'Ferias']);
        tenancy()->end();

        $usuarioB = $this->usuarioConfiguracion($tenantB);
        $this->loginAs($usuarioB);

        $this->postJson('/canales-captacion', ['nombre' => 'Ferias'])->assertCreated();
    }

    public function test_borrado_desactiva_si_tiene_leads_asociados(): void
    {
        $tenant = Tenant::factory()->create();
        $usuario = $this->usuarioConfiguracion($tenant);

        tenancy()->initialize($tenant);
        $canal = CanalCaptacion::factory()->create(['tenant_id' => $tenant->id]);
        Lead::factory()->create(['tenant_id' => $tenant->id, 'canal_captacion_id' => $canal->id]);
        tenancy()->end();

        $this->loginAs($usuario);

        $response = $this->deleteJson("/canales-captacion/{$canal->id}");

        $response->assertOk();
        $response->assertJsonPath('desactivado', true);
        $this->assertDatabaseHas('canales_captacion', ['id' => $canal->id, 'activo' => false]);
        $this->assertDatabaseHas('leads', ['id' => Lead::first()->id, 'canal_captacion_id' => $canal->id]);
    }

    public function test_borrado_elimina_si_no_tiene_leads(): void
    {
        $tenant = Tenant::factory()->create();
        $usuario = $this->usuarioConfiguracion($tenant);

        tenancy()->initialize($tenant);
        $canal = CanalCaptacion::factory()->create(['tenant_id' => $tenant->id]);
        tenancy()->end();

        $this->loginAs($usuario);

        $response = $this->deleteJson("/canales-captacion/{$canal->id}");

        $response->assertOk();
        $response->assertJsonPath('desactivado', false);
        $this->assertDatabaseMissing('canales_captacion', ['id' => $canal->id]);
    }

    public function test_aislamiento_entre_tenants(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        tenancy()->initialize($tenantA);
        $canalA = CanalCaptacion::factory()->create(['tenant_id' => $tenantA->id]);
        tenancy()->end();

        $usuarioB = $this->usuarioConfiguracion($tenantB);
        $this->loginAs($usuarioB);

        $this->getJson('/canales-captacion?solo_activos=1')->assertJsonMissing(['id' => $canalA->id]);
        $this->putJson("/canales-captacion/{$canalA->id}", ['nombre' => 'Hackeado'])->assertNotFound();
    }

    public function test_tenants_nuevos_reciben_el_catalogo_por_defecto(): void
    {
        $tenant = Tenant::factory()->create();

        tenancy()->initialize($tenant);
        \App\Support\SembradorCanalesCaptacion::sembrar($tenant->id);
        $cantidad = CanalCaptacion::count();
        tenancy()->end();

        $this->assertSame(count(\App\Support\SembradorCanalesCaptacion::NOMBRES), $cantidad);
    }
}
