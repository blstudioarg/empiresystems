<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\Tenant;
use App\Models\User;
use App\Support\CatalogoPermisos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Tests\Concerns\GestionaRolesDeTenant;
use Tests\TestCase;

class InformeComercialExportacionTest extends TestCase
{
    use GestionaRolesDeTenant, RefreshDatabase;

    private function guardarYLeerHoja(TestResponse $response, string $hoja): array
    {
        [$spreadsheet, $ruta] = $this->cargarSpreadsheet($response);
        $filas = $spreadsheet->getSheetByName($hoja)?->toArray() ?? [];

        unlink($ruta);

        return $filas;
    }

    /**
     * @return array{0: Spreadsheet, 1: string}
     */
    private function cargarSpreadsheet(TestResponse $response): array
    {
        $ruta = tempnam(sys_get_temp_dir(), 'informe-comercial-').'.xlsx';
        file_put_contents($ruta, $response->streamedContent());

        return [IOFactory::load($ruta), $ruta];
    }

    public function test_el_fichero_refleja_periodo_filtros_e_indicadores_de_la_pantalla(): void
    {
        $tenant = Tenant::factory()->create();
        $this->sembrarPermisos();
        $rol = $this->crearRol($tenant, 'Administrador', CatalogoPermisos::claves());
        $usuario = $this->usuarioConRol($tenant, $rol);

        tenancy()->initialize($tenant);
        Lead::factory()->count(6)->create(['tenant_id' => $tenant->id, 'created_at' => now()]);
        tenancy()->end();

        $this->loginAs($usuario);

        $response = $this->post('/informes-comerciales/exportar', ['preset' => 'mes']);

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $filasLeads = $this->guardarYLeerHoja($response, 'Leads');
        $fila = collect($filasLeads)->firstWhere(0, 'Leads captados');

        $this->assertNotNull($fila);
        $this->assertSame(6, (int) $fila[1]);
    }

    public function test_usuario_con_alcance_restringido_exporta_solo_sus_datos(): void
    {
        $tenant = Tenant::factory()->create();
        $this->sembrarPermisos();
        $rol = $this->crearRol($tenant, 'Comercial', ['ver-informes-comerciales', 'ver-leads', 'ver-oportunidades']);
        $comercialA = $this->usuarioConRol($tenant, $rol);
        $comercialB = User::factory()->create(['tenant_id' => $tenant->id]);

        tenancy()->initialize($tenant);
        Lead::factory()->create(['tenant_id' => $tenant->id, 'asignado_a' => $comercialA->id, 'created_at' => now()]);
        Lead::factory()->count(5)->create(['tenant_id' => $tenant->id, 'asignado_a' => $comercialB->id, 'created_at' => now()]);
        tenancy()->end();

        $this->loginAs($comercialA);

        $response = $this->post('/informes-comerciales/exportar', ['preset' => 'mes']);
        $response->assertOk();

        [$spreadsheet, $ruta] = $this->cargarSpreadsheet($response);
        $filasLeads = $spreadsheet->getSheetByName('Leads')?->toArray() ?? [];
        $fila = collect($filasLeads)->firstWhere(0, 'Leads captados');

        $this->assertSame(1, (int) $fila[1]);
        // Sin acceso a presupuestos: la hoja no debe existir (FR-028).
        $this->assertNull($spreadsheet->getSheetByName('Presupuestos'));

        unlink($ruta);
    }

    public function test_exportacion_aisla_datos_entre_tenants(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $this->sembrarPermisos();
        $rolA = $this->crearRol($tenantA, 'Administrador', CatalogoPermisos::claves());
        $usuarioA = $this->usuarioConRol($tenantA, $rolA);

        tenancy()->initialize($tenantA);
        Lead::factory()->count(3)->create(['tenant_id' => $tenantA->id, 'created_at' => now()]);
        tenancy()->end();

        tenancy()->initialize($tenantB);
        Lead::factory()->count(20)->create(['tenant_id' => $tenantB->id, 'created_at' => now()]);
        tenancy()->end();

        $this->loginAs($usuarioA);

        $response = $this->post('/informes-comerciales/exportar', ['preset' => 'mes']);
        $response->assertOk();

        $filasLeads = $this->guardarYLeerHoja($response, 'Leads');
        $fila = collect($filasLeads)->firstWhere(0, 'Leads captados');

        $this->assertSame(3, (int) $fila[1]);
    }

    public function test_periodo_sin_actividad_produce_fichero_valido_con_ceros(): void
    {
        $tenant = Tenant::factory()->create();
        $this->sembrarPermisos();
        $rol = $this->crearRol($tenant, 'Administrador', CatalogoPermisos::claves());
        $usuario = $this->usuarioConRol($tenant, $rol);

        $this->loginAs($usuario);

        $response = $this->post('/informes-comerciales/exportar', ['preset' => 'mes']);

        $response->assertOk();

        $filasLeads = $this->guardarYLeerHoja($response, 'Leads');
        $fila = collect($filasLeads)->firstWhere(0, 'Leads captados');

        $this->assertSame(0, (int) $fila[1]);
    }
}
