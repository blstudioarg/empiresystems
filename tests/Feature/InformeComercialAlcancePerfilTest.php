<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\Tenant;
use App\Models\User;
use App\Support\CatalogoPermisos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\GestionaRolesDeTenant;
use Tests\TestCase;

class InformeComercialAlcancePerfilTest extends TestCase
{
    use GestionaRolesDeTenant, RefreshDatabase;

    public function test_la_pagina_renderiza_para_usuario_con_permiso(): void
    {
        $tenant = Tenant::factory()->create();
        $this->sembrarPermisos();
        $rol = $this->crearRol($tenant, 'Administrador', CatalogoPermisos::claves());
        $usuario = $this->usuarioConRol($tenant, $rol);

        $this->loginAs($usuario);

        $response = $this->get('/informes-comerciales');

        $response->assertOk();
        $response->assertSee('Informes comerciales');
    }

    public function test_usuario_con_ver_informes_equipo_ve_todo_el_tenant_y_filtro_por_comercial(): void
    {
        $tenant = Tenant::factory()->create();
        $this->sembrarPermisos();
        $rol = $this->crearRol($tenant, 'Jefe de ventas', [
            'ver-informes-comerciales', 'ver-informes-equipo', 'ver-leads', 'ver-oportunidades', 'ver-presupuestos',
        ]);
        $jefe = $this->usuarioConRol($tenant, $rol);
        $comercial = User::factory()->create(['tenant_id' => $tenant->id]);

        tenancy()->initialize($tenant);
        Lead::factory()->create(['tenant_id' => $tenant->id, 'asignado_a' => $comercial->id, 'created_at' => now()]);
        Lead::factory()->create(['tenant_id' => $tenant->id, 'asignado_a' => $jefe->id, 'created_at' => now()]);
        tenancy()->end();

        $this->loginAs($jefe);

        $json = $this->getJson('/informes-comerciales', ['Accept' => 'application/json']);
        $json->assertOk();
        $json->assertJsonPath('alcance.tipo', 'tenant');

        $pagina = $this->get('/informes-comerciales');
        $pagina->assertOk();
        $pagina->assertSee('id="informe-comercial-comercial"', false);
    }

    public function test_usuario_sin_ver_informes_equipo_ve_solo_su_actividad_asignada(): void
    {
        $tenant = Tenant::factory()->create();
        $this->sembrarPermisos();
        $rol = $this->crearRol($tenant, 'Comercial', [
            'ver-informes-comerciales', 'ver-leads', 'ver-oportunidades', 'ver-presupuestos',
        ]);
        $comercialA = $this->usuarioConRol($tenant, $rol);
        $comercialB = User::factory()->create(['tenant_id' => $tenant->id]);

        tenancy()->initialize($tenant);
        Lead::factory()->create(['tenant_id' => $tenant->id, 'asignado_a' => $comercialA->id, 'created_at' => now()]);
        Lead::factory()->count(4)->create(['tenant_id' => $tenant->id, 'asignado_a' => $comercialB->id, 'created_at' => now()]);
        tenancy()->end();

        $this->loginAs($comercialA);

        $response = $this->getJson('/informes-comerciales', ['Accept' => 'application/json']);

        $response->assertOk();
        $response->assertJsonPath('alcance.tipo', 'propio');
    }

    public function test_forzar_comercial_id_ajeno_no_devuelve_datos_de_otro_usuario(): void
    {
        $tenant = Tenant::factory()->create();
        $this->sembrarPermisos();
        $rol = $this->crearRol($tenant, 'Comercial', [
            'ver-informes-comerciales', 'ver-leads', 'ver-oportunidades', 'ver-presupuestos',
        ]);
        $comercialA = $this->usuarioConRol($tenant, $rol);
        $comercialB = User::factory()->create(['tenant_id' => $tenant->id]);

        tenancy()->initialize($tenant);
        Lead::factory()->count(9)->create(['tenant_id' => $tenant->id, 'asignado_a' => $comercialB->id, 'created_at' => now()]);
        tenancy()->end();

        $this->loginAs($comercialA);

        $response = $this->getJson('/informes-comerciales?comercial_id='.$comercialB->id, ['Accept' => 'application/json']);

        $response->assertOk();
        $this->assertStringNotContainsString('9', $response->json('html') ?? '');
    }

    public function test_bloques_sin_permiso_no_aparecen(): void
    {
        $tenant = Tenant::factory()->create();
        $this->sembrarPermisos();
        $rol = $this->crearRol($tenant, 'Solo leads', ['ver-informes-comerciales', 'ver-leads']);
        $usuario = $this->usuarioConRol($tenant, $rol);

        $this->loginAs($usuario);

        $response = $this->getJson('/informes-comerciales', ['Accept' => 'application/json']);

        $response->assertOk();
        $this->assertEmpty($response->json('graficos.presupuestos_por_estado'));
        $this->assertEmpty($response->json('graficos.oportunidades_por_etapa'));
    }

    public function test_sin_ningun_permiso_de_modulo_no_accede(): void
    {
        $tenant = Tenant::factory()->create();
        $this->sembrarPermisos();
        $rol = $this->crearRol($tenant, 'Sin acceso CRM', ['ver-informes-comerciales']);
        $usuario = $this->usuarioConRol($tenant, $rol);

        $this->loginAs($usuario);

        $response = $this->get('/informes-comerciales');

        $response->assertForbidden();
    }

    public function test_retirar_permiso_surte_efecto_en_la_siguiente_peticion(): void
    {
        $tenant = Tenant::factory()->create();
        $this->sembrarPermisos();
        $rol = $this->crearRol($tenant, 'Jefe', [
            'ver-informes-comerciales', 'ver-informes-equipo', 'ver-leads', 'ver-oportunidades', 'ver-presupuestos',
        ]);
        $usuario = $this->usuarioConRol($tenant, $rol);

        $this->loginAs($usuario);

        $primera = $this->getJson('/informes-comerciales', ['Accept' => 'application/json']);
        $primera->assertOk();
        $primera->assertJsonPath('alcance.tipo', 'tenant');

        $this->crearRol($tenant, 'Jefe', ['ver-informes-comerciales', 'ver-leads', 'ver-oportunidades', 'ver-presupuestos']);

        $segunda = $this->getJson('/informes-comerciales', ['Accept' => 'application/json']);
        $segunda->assertOk();
        $segunda->assertJsonPath('alcance.tipo', 'propio');
    }
}
