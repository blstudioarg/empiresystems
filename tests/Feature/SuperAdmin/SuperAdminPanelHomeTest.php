<?php

namespace Tests\Feature\SuperAdmin;

use App\Enums\EstadoFactura;
use App\Enums\EstadoUsuario;
use App\Models\Cliente;
use App\Models\Factura;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * US2 (037-super-admin-panel-aislado): home del panel de Super Admin con estadísticas de
 * tenants. Contrato de forma en specs/037-super-admin-panel-aislado/contracts/panel-home.md.
 */
class SuperAdminPanelHomeTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_devuelve_totales_exactos_de_un_conjunto_conocido_de_tenants(): void
    {
        $activo1 = Tenant::factory()->create(['activo' => true, 'created_at' => now()->startOfMonth()->addDay()]);
        $activo2 = Tenant::factory()->create(['activo' => true, 'created_at' => now()->subMonths(2)]);
        Tenant::factory()->create(['activo' => false, 'created_at' => now()->subMonths(5)]);

        User::factory()->create(['tenant_id' => $activo1->id]);
        User::factory()->create(['tenant_id' => $activo1->id]);
        User::factory()->create(['tenant_id' => $activo2->id]);

        $superAdmin = User::factory()->superAdmin()->create(['password' => bcrypt('secret123')]);
        $this->loginAs($superAdmin);

        $response = $this->get(route('super_admin.home'));

        $response->assertOk();
        $datos = $response->viewData('datos');

        $this->assertSame(3, $datos['totales']['tenants']);
        $this->assertSame(2, $datos['totales']['activos']);
        $this->assertSame(1, $datos['totales']['inactivos']);
        $this->assertSame(1, $datos['totales']['altas_mes']);
        $this->assertSame(3, $datos['totales']['usuarios']);
        $this->assertSame($datos['totales']['tenants'], $datos['totales']['activos'] + $datos['totales']['inactivos']);
    }

    public function test_el_super_admin_no_se_cuenta_en_el_total_de_usuarios(): void
    {
        $tenant = Tenant::factory()->create();
        User::factory()->create(['tenant_id' => $tenant->id]);

        $superAdmin = User::factory()->superAdmin()->create(['password' => bcrypt('secret123')]);
        $this->loginAs($superAdmin);

        $datos = $this->get(route('super_admin.home'))->viewData('datos');

        $this->assertSame(1, $datos['totales']['usuarios']);
    }

    public function test_serie_altas_tiene_siempre_12_meses_incluidos_los_vacios(): void
    {
        Tenant::factory()->create(['created_at' => now()]);
        Tenant::factory()->create(['created_at' => now()->subMonths(11)]);

        $superAdmin = User::factory()->superAdmin()->create(['password' => bcrypt('secret123')]);
        $this->loginAs($superAdmin);

        $datos = $this->get(route('super_admin.home'))->viewData('datos');

        $this->assertCount(12, $datos['serie_altas']);
        $this->assertSame(2, array_sum(array_column($datos['serie_altas'], 'valor')));
    }

    public function test_cero_tenants_devuelve_estado_vacio_consistente(): void
    {
        $superAdmin = User::factory()->superAdmin()->create(['password' => bcrypt('secret123')]);
        $this->loginAs($superAdmin);

        $datos = $this->get(route('super_admin.home'))->viewData('datos');

        $this->assertSame(0, $datos['totales']['tenants']);
        $this->assertSame(0, $datos['totales']['activos']);
        $this->assertSame(0, $datos['totales']['inactivos']);
        $this->assertSame(0, $datos['totales']['altas_mes']);
        $this->assertSame(0, $datos['totales']['usuarios']);
        $this->assertCount(12, $datos['serie_altas']);
        $this->assertSame(0, array_sum(array_column($datos['serie_altas'], 'valor')));
        $this->assertSame([], $datos['ultimos_tenants']);
        $this->assertSame([], $datos['ranking_tamano']);
        $this->assertSame([], $datos['atencion']);
    }

    public function test_ultimos_tenants_creados_con_nombre_dominio_estado_y_fecha(): void
    {
        // TenantFactory ya crea automáticamente un dominio propio (afterCreating): no hace falta
        // (ni conviene) crear uno segundo a mano, se usa el que ya quedó asociado.
        $tenant = Tenant::factory()->create(['nombre_comercial' => 'BL Studio', 'activo' => true]);
        $dominioEsperado = $tenant->dominio()->domain;

        $superAdmin = User::factory()->superAdmin()->create(['password' => bcrypt('secret123')]);
        $this->loginAs($superAdmin);

        $datos = $this->get(route('super_admin.home'))->viewData('datos');

        $this->assertCount(1, $datos['ultimos_tenants']);
        $fila = $datos['ultimos_tenants'][0];
        $this->assertSame($tenant->id, $fila['id']);
        $this->assertSame('BL Studio', $fila['nombre']);
        $this->assertSame($dominioEsperado, $fila['dominio']);
        $this->assertTrue($fila['activo']);
        $this->assertSame($tenant->created_at->format('d/m/Y'), $fila['alta']);
        $this->assertSame(route('super_admin.tenants.index'), $fila['gestion_url']);
    }

    public function test_ultimos_tenants_se_limita_a_5_y_ordena_por_alta_descendente(): void
    {
        foreach (range(1, 7) as $i) {
            Tenant::factory()->create(['created_at' => now()->subDays($i)]);
        }

        $superAdmin = User::factory()->superAdmin()->create(['password' => bcrypt('secret123')]);
        $this->loginAs($superAdmin);

        $datos = $this->get(route('super_admin.home'))->viewData('datos');

        $this->assertCount(5, $datos['ultimos_tenants']);
        $fechas = array_column($datos['ultimos_tenants'], 'alta');
        $ordenadas = $fechas;
        usort($ordenadas, fn ($a, $b) => \Carbon\Carbon::createFromFormat('d/m/Y', $b) <=> \Carbon\Carbon::createFromFormat('d/m/Y', $a));
        $this->assertSame($ordenadas, $fechas);
    }

    public function test_ranking_por_tamano_no_expone_datos_de_negocio_solo_recuentos(): void
    {
        $tenantA = Tenant::factory()->create(['nombre_comercial' => 'Tenant A']);
        $tenantB = Tenant::factory()->create(['nombre_comercial' => 'Tenant B']);

        User::factory()->count(3)->create(['tenant_id' => $tenantA->id]);
        User::factory()->count(1)->create(['tenant_id' => $tenantB->id]);

        $facturaEmitida = Factura::factory()->emitida()->create(['tenant_id' => $tenantA->id]);
        Factura::factory()->emitida()->create(['tenant_id' => $tenantA->id]);
        Factura::factory()->create([
            'tenant_id' => $tenantA->id,
            'estado' => EstadoFactura::Borrador,
        ]);
        Factura::factory()->emitida()->create(['tenant_id' => $tenantB->id]);

        Cliente::factory()->create(['tenant_id' => $tenantA->id, 'nombre' => 'Cliente Secreto de A']);

        $superAdmin = User::factory()->superAdmin()->create(['password' => bcrypt('secret123')]);
        $this->loginAs($superAdmin);

        $response = $this->get(route('super_admin.home'));
        $datos = $response->viewData('datos');

        $rankingA = collect($datos['ranking_tamano'])->firstWhere('id', $tenantA->id);
        $rankingB = collect($datos['ranking_tamano'])->firstWhere('id', $tenantB->id);

        $this->assertSame(3, $rankingA['usuarios']);
        $this->assertSame(2, $rankingA['documentos']); // borrador no cuenta
        $this->assertSame(1, $rankingB['usuarios']);
        $this->assertSame(1, $rankingB['documentos']);

        // FR-019: la home no expone nada de negocio, solo recuentos agregados.
        $response->assertDontSee('Cliente Secreto de A');
        $response->assertDontSee($facturaEmitida->numero_completo);
    }

    public function test_gate_login_ver_super_admin_home_requiere_super_admin(): void
    {
        Tenant::factory()->create();

        $tenant = Tenant::factory()->create();
        $usuario = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $this->loginAs($usuario);

        $this->get('http://localhost/super_admin')->assertForbidden();
    }
}
