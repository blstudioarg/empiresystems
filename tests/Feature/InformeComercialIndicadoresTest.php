<?php

namespace Tests\Feature;

use App\Enums\EstadoLead;
use App\Enums\EstadoPresupuesto;
use App\Enums\EtapaOportunidad;
use App\Models\Cliente;
use App\Models\Factura;
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

class InformeComercialIndicadoresTest extends TestCase
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

    private function generar(RangoFechas $rango, AlcanceInformeComercial $alcance, array $filtros = []): array
    {
        return (new InformeComercial())->generar($rango, FiltrosInforme::desdePeticion($filtros), $alcance);
    }

    public function test_indicadores_de_volumen_del_periodo(): void
    {
        $tenant = Tenant::factory()->create();
        tenancy()->initialize($tenant);
        $alcance = $this->alcanceTenant($tenant);

        $rango = RangoFechas::personalizado(Carbon::parse('2026-06-01'), Carbon::parse('2026-06-30'));
        $cliente = Cliente::factory()->create(['tenant_id' => $tenant->id]);

        Lead::factory()->count(7)->create(['tenant_id' => $tenant->id, 'created_at' => '2026-06-05']);
        Lead::factory()->count(3)->convertido()->create([
            'tenant_id' => $tenant->id, 'created_at' => '2026-06-10', 'convertido_at' => '2026-06-15',
        ]);

        Oportunidad::factory()->count(2)->create([
            'tenant_id' => $tenant->id, 'cliente_id' => $cliente->id, 'created_at' => '2026-06-05',
            'importe_estimado' => 100, 'cerrada_at' => null,
        ]);
        Oportunidad::factory()->ganada()->count(4)->create([
            'tenant_id' => $tenant->id, 'cliente_id' => $cliente->id, 'created_at' => '2026-05-01', 'cerrada_at' => '2026-06-10',
        ]);
        Oportunidad::factory()->perdida()->count(6)->create([
            'tenant_id' => $tenant->id, 'cliente_id' => $cliente->id, 'created_at' => '2026-05-01', 'cerrada_at' => '2026-06-12',
        ]);
        Oportunidad::factory()->create([
            'tenant_id' => $tenant->id, 'cliente_id' => $cliente->id, 'created_at' => '2026-06-01',
            'importe_estimado' => 500, 'cerrada_at' => null,
        ]);

        Presupuesto::factory()->count(5)->create(['tenant_id' => $tenant->id, 'cliente_id' => $cliente->id, 'fecha_emision' => '2026-06-15', 'total' => 100]);
        Presupuesto::factory()->aceptado()->count(2)->create(['tenant_id' => $tenant->id, 'cliente_id' => $cliente->id, 'fecha_emision' => '2026-06-16', 'total' => 200]);

        $datos = $this->generar($rango, $alcance);

        $this->assertSame(10, $datos['indicadores']['leads_captados']);
        $this->assertSame(3, $datos['indicadores']['leads_convertidos']);
        // Cohorte por created_at: solo las 2 + la abierta caen en junio; las ganadas/perdidas se
        // crearon en mayo (solo su cierre, cerrada_at, cae en junio).
        $this->assertSame(3, $datos['indicadores']['oportunidades_creadas']);
        $this->assertSame(4, $datos['indicadores']['oportunidades_ganadas']);
        $this->assertSame(6, $datos['indicadores']['oportunidades_perdidas']);
        // Abiertas a fecha de corte: las 2 sin cerrar (100 c/u) + la explícita (500).
        $this->assertSame(3, $datos['indicadores']['oportunidades_abiertas']);
        $this->assertEquals(700.0, $datos['indicadores']['importe_pipeline']);
        $this->assertSame(7, $datos['indicadores']['presupuestos_emitidos']);
        $this->assertEquals(500 + 400, $datos['indicadores']['importe_presupuestado']);
        $this->assertSame(2, $datos['indicadores']['presupuestos_aceptados']);
        $this->assertEquals(400.0, $datos['indicadores']['importe_aceptado']);

        tenancy()->end();
    }

    public function test_registros_fuera_del_rango_no_cuentan(): void
    {
        $tenant = Tenant::factory()->create();
        tenancy()->initialize($tenant);
        $alcance = $this->alcanceTenant($tenant);

        $rango = RangoFechas::personalizado(Carbon::parse('2026-06-01'), Carbon::parse('2026-06-30'));

        Lead::factory()->create(['tenant_id' => $tenant->id, 'created_at' => '2026-05-31 23:59:59']);
        Lead::factory()->create(['tenant_id' => $tenant->id, 'created_at' => '2026-07-01 00:00:00']);
        Lead::factory()->create(['tenant_id' => $tenant->id, 'created_at' => '2026-06-15']);

        $datos = $this->generar($rango, $alcance);

        $this->assertSame(1, $datos['indicadores']['leads_captados']);

        tenancy()->end();
    }

    public function test_criterio_cohorte_evento_e_instantanea(): void
    {
        $tenant = Tenant::factory()->create();
        tenancy()->initialize($tenant);
        $alcance = $this->alcanceTenant($tenant);
        $cliente = Cliente::factory()->create(['tenant_id' => $tenant->id]);

        $rango = RangoFechas::personalizado(Carbon::parse('2026-06-01'), Carbon::parse('2026-06-30'));

        // Cohorte: oportunidad creada en el periodo pero cerrada después no cuenta como ganada
        // (evento), pero sí cuenta como creada (cohorte).
        Oportunidad::factory()->create([
            'tenant_id' => $tenant->id, 'cliente_id' => $cliente->id,
            'created_at' => '2026-06-05', 'etapa' => EtapaOportunidad::Ganada->value, 'cerrada_at' => '2026-07-10',
        ]);

        // Evento: oportunidad creada antes del periodo pero cerrada dentro sí cuenta como ganada.
        Oportunidad::factory()->ganada()->create([
            'tenant_id' => $tenant->id, 'cliente_id' => $cliente->id,
            'created_at' => '2026-01-01', 'cerrada_at' => '2026-06-20',
        ]);

        // Instantánea: abierta a fecha de corte (creada antes de hasta, sin cerrar todavía).
        Oportunidad::factory()->create([
            'tenant_id' => $tenant->id, 'cliente_id' => $cliente->id,
            'created_at' => '2026-06-01', 'cerrada_at' => null, 'importe_estimado' => 1000,
        ]);

        $datos = $this->generar($rango, $alcance);

        $this->assertSame(2, $datos['indicadores']['oportunidades_creadas']);
        $this->assertSame(1, $datos['indicadores']['oportunidades_ganadas']);
        // Abiertas a fecha de corte (30/06): la primera (cerrada recién en julio) + la sin cerrar.
        $this->assertSame(2, $datos['indicadores']['oportunidades_abiertas']);

        tenancy()->end();
    }

    public function test_negocio_cerrado_del_embudo_excluye_alta_directa_y_simplificada(): void
    {
        $tenant = Tenant::factory()->create();
        tenancy()->initialize($tenant);
        $alcance = $this->alcanceTenant($tenant);
        $cliente = Cliente::factory()->create(['tenant_id' => $tenant->id]);

        $rango = RangoFechas::personalizado(Carbon::parse('2026-06-01'), Carbon::parse('2026-06-30'));

        $facturaDelEmbudo = Factura::factory()->emitida()->create([
            'tenant_id' => $tenant->id, 'cliente_id' => $cliente->id,
            'fecha_expedicion' => '2026-06-20', 'total' => 300,
        ]);
        Presupuesto::factory()->facturado()->create([
            'tenant_id' => $tenant->id, 'cliente_id' => $cliente->id,
            'fecha_emision' => '2026-06-18', 'total' => 300,
            'convertido_a_factura_id' => $facturaDelEmbudo->id,
        ]);

        // Alta directa: sin presupuesto que la referencie.
        Factura::factory()->emitida()->create([
            'tenant_id' => $tenant->id, 'cliente_id' => $cliente->id,
            'fecha_expedicion' => '2026-06-21', 'total' => 999,
        ]);

        // Simplificada (POS): tampoco referenciada por ningún presupuesto.
        Factura::factory()->emitida()->create([
            'tenant_id' => $tenant->id, 'cliente_id' => $cliente->id,
            'tipo' => 'simplificada', 'fecha_expedicion' => '2026-06-22', 'total' => 50,
        ]);

        $datos = $this->generar($rango, $alcance);

        $this->assertSame(1, $datos['indicadores']['facturas_del_embudo']);
        $this->assertEquals(300.0, $datos['indicadores']['importe_facturado_embudo']);

        tenancy()->end();
    }

    public function test_borrado_logico_no_cuenta(): void
    {
        $tenant = Tenant::factory()->create();
        tenancy()->initialize($tenant);
        $alcance = $this->alcanceTenant($tenant);

        $rango = RangoFechas::personalizado(Carbon::parse('2026-06-01'), Carbon::parse('2026-06-30'));

        Lead::factory()->create(['tenant_id' => $tenant->id, 'created_at' => '2026-06-05']);
        $borrado = Lead::factory()->create(['tenant_id' => $tenant->id, 'created_at' => '2026-06-06']);
        $borrado->delete();

        $datos = $this->generar($rango, $alcance);

        $this->assertSame(1, $datos['indicadores']['leads_captados']);

        tenancy()->end();
    }

    public function test_comercial_dado_de_baja_sigue_visible_en_periodos_pasados(): void
    {
        $tenant = Tenant::factory()->create();
        tenancy()->initialize($tenant);
        $alcance = $this->alcanceTenant($tenant);

        $comercial = \App\Models\User::factory()->create(['tenant_id' => $tenant->id, 'activo' => false]);
        $rango = RangoFechas::personalizado(Carbon::parse('2026-06-01'), Carbon::parse('2026-06-30'));

        Lead::factory()->create(['tenant_id' => $tenant->id, 'created_at' => '2026-06-05', 'asignado_a' => $comercial->id]);

        $datos = $this->generar($rango, $alcance, ['comercial_id' => $comercial->id]);

        $this->assertSame(1, $datos['indicadores']['leads_captados']);

        tenancy()->end();
    }
}
