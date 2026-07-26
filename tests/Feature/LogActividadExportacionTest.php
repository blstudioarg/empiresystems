<?php

namespace Tests\Feature;

use App\Excel\ExportacionModulo;
use App\Models\LogActividad;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class LogActividadExportacionTest extends TestCase
{
    use RefreshDatabase;

    public function test_exporta_solo_los_ids_recibidos(): void
    {
        Excel::fake();

        $tenant = Tenant::factory()->create();
        $user = User::factory()->admin()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);

        $logs = LogActividad::factory()->for($tenant)->count(3)->create();
        $incluidos = $logs->take(2);

        $this->loginAs($user);

        $response = $this->postJson('/exportar/logs', ['ids' => $incluidos->pluck('id')->all()]);

        $response->assertOk();

        Excel::assertDownloaded(
            'logs-'.now()->format('Y-m-d').'.xlsx',
            function (ExportacionModulo $export) use ($incluidos) {
                $ids = $export->query()->pluck('id')->sort()->values()->all();
                $esperados = $incluidos->pluck('id')->sort()->values()->all();

                return $ids === $esperados;
            }
        );
    }

    /**
     * Principio I + IV: test de aislamiento multi-tenant, escrito antes de la implementación.
     */
    public function test_no_exporta_filas_de_otro_tenant_aunque_se_manden_sus_ids(): void
    {
        Excel::fake();

        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $userA = User::factory()->admin()->create(['tenant_id' => $tenantA->id, 'password' => bcrypt('secret123')]);

        $logA = LogActividad::factory()->for($tenantA)->create();
        $logB = LogActividad::factory()->for($tenantB)->create();

        $this->loginAs($userA);

        $response = $this->postJson('/exportar/logs', ['ids' => [$logA->id, $logB->id]]);

        $response->assertOk();

        Excel::assertDownloaded(
            'logs-'.now()->format('Y-m-d').'.xlsx',
            function (ExportacionModulo $export) use ($logA) {
                return $export->query()->pluck('id')->all() === [$logA->id];
            }
        );
    }

    public function test_registra_actividad_de_la_propia_exportacion(): void
    {
        Excel::fake();

        $tenant = Tenant::factory()->create();
        $user = User::factory()->admin()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $log = LogActividad::factory()->for($tenant)->create();

        $this->loginAs($user);

        $this->postJson('/exportar/logs', ['ids' => [$log->id]])->assertOk();

        $this->assertDatabaseHas('logs_actividad', [
            'tenant_id' => $tenant->id,
            'accion' => 'exportacion',
            'entidad_tipo' => 'log_actividad',
            'descripcion' => 'Exportó 1 logs de actividad a Excel',
        ]);
    }

    public function test_incluye_el_id_para_poder_seleccionar_filas_desde_el_listado(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->admin()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $log = LogActividad::factory()->for($tenant)->create();

        // loginAs() genera su propia fila de log (evento "login"), así que en vez de comparar la
        // primera fila comprobamos que el id del evento creado a mano aparece en la respuesta.
        $this->loginAs($user);

        $response = $this->getJson('/logs?'.http_build_query([
            'draw' => 1,
            'start' => 0,
            'length' => 10,
            'search' => ['value' => ''],
        ]));

        $response->assertOk();
        $this->assertContains($log->id, collect($response->json('data'))->pluck('id')->all());
    }
}
