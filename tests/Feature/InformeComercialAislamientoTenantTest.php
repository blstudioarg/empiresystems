<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Lead;
use App\Models\Oportunidad;
use App\Models\Presupuesto;
use App\Models\Tenant;
use App\Services\InformeComercial;
use App\Support\AlcanceInformeComercial;
use App\Support\FiltrosInforme;
use App\Support\RangoFechas;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\GestionaRolesDeTenant;
use Tests\TestCase;

class InformeComercialAislamientoTenantTest extends TestCase
{
    use GestionaRolesDeTenant, RefreshDatabase;

    private function alcanceTenant(Tenant $tenant): AlcanceInformeComercial
    {
        $this->sembrarPermisos();
        $rol = $this->crearRol($tenant, 'Administrador', \App\Support\CatalogoPermisos::claves());
        $usuario = $this->usuarioConRol($tenant, $rol);

        app(\Spatie\Permission\PermissionRegistrar::class)->setPermissionsTeamId($tenant->getTenantKey());

        return AlcanceInformeComercial::paraUsuario($usuario);
    }

    public function test_ningun_indicador_cruza_entre_tenants(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $rango = RangoFechas::personalizado(Carbon::parse('2026-06-01'), Carbon::parse('2026-06-30'));

        tenancy()->initialize($tenantA);
        $alcanceA = $this->alcanceTenant($tenantA);
        $clienteA = Cliente::factory()->create(['tenant_id' => $tenantA->id]);
        Lead::factory()->count(5)->create(['tenant_id' => $tenantA->id, 'created_at' => '2026-06-05']);
        Oportunidad::factory()->count(3)->create(['tenant_id' => $tenantA->id, 'cliente_id' => $clienteA->id, 'created_at' => '2026-06-05']);
        Presupuesto::factory()->count(2)->create(['tenant_id' => $tenantA->id, 'cliente_id' => $clienteA->id, 'fecha_emision' => '2026-06-10', 'total' => 100]);
        tenancy()->end();

        tenancy()->initialize($tenantB);
        $alcanceB = $this->alcanceTenant($tenantB);
        $clienteB = Cliente::factory()->create(['tenant_id' => $tenantB->id]);
        Lead::factory()->count(9)->create(['tenant_id' => $tenantB->id, 'created_at' => '2026-06-05']);
        Oportunidad::factory()->count(7)->create(['tenant_id' => $tenantB->id, 'cliente_id' => $clienteB->id, 'created_at' => '2026-06-05']);
        Presupuesto::factory()->count(4)->create(['tenant_id' => $tenantB->id, 'cliente_id' => $clienteB->id, 'fecha_emision' => '2026-06-10', 'total' => 500]);
        tenancy()->end();

        tenancy()->initialize($tenantA);
        $datosA = (new InformeComercial())->generar($rango, FiltrosInforme::desdePeticion([]), $alcanceA);
        tenancy()->end();

        tenancy()->initialize($tenantB);
        $datosB = (new InformeComercial())->generar($rango, FiltrosInforme::desdePeticion([]), $alcanceB);
        tenancy()->end();

        $this->assertSame(5, $datosA['indicadores']['leads_captados']);
        $this->assertSame(3, $datosA['indicadores']['oportunidades_creadas']);
        $this->assertSame(2, $datosA['indicadores']['presupuestos_emitidos']);
        $this->assertEquals(200.0, $datosA['indicadores']['importe_presupuestado']);

        $this->assertSame(9, $datosB['indicadores']['leads_captados']);
        $this->assertSame(7, $datosB['indicadores']['oportunidades_creadas']);
        $this->assertSame(4, $datosB['indicadores']['presupuestos_emitidos']);
        $this->assertEquals(2000.0, $datosB['indicadores']['importe_presupuestado']);
    }
}
