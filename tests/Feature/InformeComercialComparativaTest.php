<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\Tenant;
use App\Services\InformeComercial;
use App\Support\AlcanceInformeComercial;
use App\Support\FiltrosInforme;
use App\Support\RangoFechas;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\GestionaRolesDeTenant;
use Tests\TestCase;

class InformeComercialComparativaTest extends TestCase
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

    public function test_comparativa_devuelve_actual_comparado_y_variacion(): void
    {
        $tenant = Tenant::factory()->create();
        tenancy()->initialize($tenant);
        $alcance = $this->alcanceTenant($tenant);

        $rango = RangoFechas::personalizado(Carbon::parse('2026-06-01'), Carbon::parse('2026-06-30'));

        Lead::factory()->count(10)->create(['tenant_id' => $tenant->id, 'created_at' => '2026-06-05']);
        Lead::factory()->count(4)->create(['tenant_id' => $tenant->id, 'created_at' => '2025-06-05']);

        $datos = (new InformeComercial())->generar($rango, FiltrosInforme::desdePeticion(['comparar' => '1']), $alcance);

        $this->assertNotNull($datos['comparativa']);
        $this->assertSame('2025-06-01', $datos['comparativa']['periodo']['desde']);
        $this->assertSame('2025-06-30', $datos['comparativa']['periodo']['hasta']);
        $this->assertSame(10, $datos['indicadores']['leads_captados']);
        $this->assertSame(4, $datos['comparativa']['indicadores']['leads_captados']);
        $this->assertEquals(150.0, $datos['comparativa']['variaciones']['indicadores']['leads_captados']);

        tenancy()->end();
    }

    public function test_ejercicio_comparado_sin_datos_da_variacion_no_calculable(): void
    {
        $tenant = Tenant::factory()->create();
        tenancy()->initialize($tenant);
        $alcance = $this->alcanceTenant($tenant);

        $rango = RangoFechas::personalizado(Carbon::parse('2026-06-01'), Carbon::parse('2026-06-30'));

        Lead::factory()->count(5)->create(['tenant_id' => $tenant->id, 'created_at' => '2026-06-05']);

        $datos = (new InformeComercial())->generar($rango, FiltrosInforme::desdePeticion(['comparar' => '1']), $alcance);

        $this->assertSame(0, $datos['comparativa']['indicadores']['leads_captados']);
        $this->assertNull($datos['comparativa']['variaciones']['indicadores']['leads_captados']);

        tenancy()->end();
    }

    public function test_series_de_evolucion_actual_y_comparada_tienen_igual_longitud(): void
    {
        $tenant = Tenant::factory()->create();
        tenancy()->initialize($tenant);
        $alcance = $this->alcanceTenant($tenant);

        $rango = RangoFechas::personalizado(Carbon::parse('2026-06-01'), Carbon::parse('2026-06-30'));

        $datos = (new InformeComercial())->generar($rango, FiltrosInforme::desdePeticion(['comparar' => '1']), $alcance);

        $this->assertSame(count($datos['evolucion']), count($datos['comparativa']['evolucion']));

        tenancy()->end();
    }
}
