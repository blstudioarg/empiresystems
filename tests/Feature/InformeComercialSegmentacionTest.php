<?php

namespace Tests\Feature;

use App\Models\CanalCaptacion;
use App\Models\Lead;
use App\Models\Tenant;
use App\Models\User;
use App\Services\InformeComercial;
use App\Support\AlcanceInformeComercial;
use App\Support\CatalogoPermisos;
use App\Support\FiltrosInforme;
use App\Support\RangoFechas;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\GestionaRolesDeTenant;
use Tests\TestCase;

class InformeComercialSegmentacionTest extends TestCase
{
    use GestionaRolesDeTenant, RefreshDatabase;

    private function alcanceTenant(Tenant $tenant): AlcanceInformeComercial
    {
        $this->sembrarPermisos();
        $rol = $this->crearRol($tenant, 'Administrador', CatalogoPermisos::claves());
        $usuario = $this->usuarioConRol($tenant, $rol);

        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->getTenantKey());

        return AlcanceInformeComercial::paraUsuario($usuario);
    }

    private function generar(RangoFechas $rango, AlcanceInformeComercial $alcance, array $filtros = []): array
    {
        return (new InformeComercial)->generar($rango, FiltrosInforme::desdePeticion($filtros), $alcance);
    }

    public function test_filtro_por_canal_los_segmentos_suman_el_total(): void
    {
        $tenant = Tenant::factory()->create();
        tenancy()->initialize($tenant);
        $alcance = $this->alcanceTenant($tenant);

        $canalWeb = CanalCaptacion::factory()->create(['tenant_id' => $tenant->id, 'nombre' => 'Web']);
        $canalFeria = CanalCaptacion::factory()->create(['tenant_id' => $tenant->id, 'nombre' => 'Feria']);

        $rango = RangoFechas::personalizado(Carbon::parse('2026-06-01'), Carbon::parse('2026-06-30'));

        Lead::factory()->count(4)->create(['tenant_id' => $tenant->id, 'canal_captacion_id' => $canalWeb->id, 'created_at' => '2026-06-05']);
        Lead::factory()->count(3)->create(['tenant_id' => $tenant->id, 'canal_captacion_id' => $canalFeria->id, 'created_at' => '2026-06-05']);
        Lead::factory()->count(2)->create(['tenant_id' => $tenant->id, 'canal_captacion_id' => null, 'created_at' => '2026-06-05']);

        $total = $this->generar($rango, $alcance);
        $web = $this->generar($rango, $alcance, ['canal_id' => $canalWeb->id]);
        $feria = $this->generar($rango, $alcance, ['canal_id' => $canalFeria->id]);
        $sinEspecificar = $this->generar($rango, $alcance, ['canal_id' => 'sin_especificar']);

        $this->assertSame(9, $total['indicadores']['leads_captados']);
        $this->assertSame(4, $web['indicadores']['leads_captados']);
        $this->assertSame(3, $feria['indicadores']['leads_captados']);
        $this->assertSame(2, $sinEspecificar['indicadores']['leads_captados']);
        $this->assertSame(
            $total['indicadores']['leads_captados'],
            $web['indicadores']['leads_captados'] + $feria['indicadores']['leads_captados'] + $sinEspecificar['indicadores']['leads_captados'],
        );

        tenancy()->end();
    }

    public function test_leads_sin_canal_se_agrupan_como_sin_especificar_y_no_se_pierden(): void
    {
        $tenant = Tenant::factory()->create();
        tenancy()->initialize($tenant);
        $alcance = $this->alcanceTenant($tenant);

        $rango = RangoFechas::personalizado(Carbon::parse('2026-06-01'), Carbon::parse('2026-06-30'));

        Lead::factory()->count(5)->create(['tenant_id' => $tenant->id, 'canal_captacion_id' => null, 'created_at' => '2026-06-05']);

        $datos = $this->generar($rango, $alcance, ['canal_id' => 'sin_especificar']);

        $this->assertSame(5, $datos['indicadores']['leads_captados']);

        tenancy()->end();
    }

    public function test_filtro_por_comercial_y_combinado_con_canal(): void
    {
        $tenant = Tenant::factory()->create();
        tenancy()->initialize($tenant);
        $alcance = $this->alcanceTenant($tenant);

        $comercialA = User::factory()->create(['tenant_id' => $tenant->id]);
        $comercialB = User::factory()->create(['tenant_id' => $tenant->id]);
        $canal = CanalCaptacion::factory()->create(['tenant_id' => $tenant->id]);

        $rango = RangoFechas::personalizado(Carbon::parse('2026-06-01'), Carbon::parse('2026-06-30'));

        Lead::factory()->create(['tenant_id' => $tenant->id, 'asignado_a' => $comercialA->id, 'canal_captacion_id' => $canal->id, 'created_at' => '2026-06-05']);
        Lead::factory()->count(2)->create(['tenant_id' => $tenant->id, 'asignado_a' => $comercialB->id, 'canal_captacion_id' => $canal->id, 'created_at' => '2026-06-05']);
        Lead::factory()->create(['tenant_id' => $tenant->id, 'asignado_a' => $comercialA->id, 'canal_captacion_id' => null, 'created_at' => '2026-06-05']);

        $porComercialA = $this->generar($rango, $alcance, ['comercial_id' => $comercialA->id]);
        $combinado = $this->generar($rango, $alcance, ['comercial_id' => $comercialA->id, 'canal_id' => $canal->id]);

        $this->assertSame(2, $porComercialA['indicadores']['leads_captados']);
        $this->assertSame(1, $combinado['indicadores']['leads_captados']);

        tenancy()->end();
    }
}
