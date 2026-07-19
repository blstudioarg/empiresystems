<?php

namespace Tests\Feature;

use App\Excel\ExportacionModulo;
use App\Models\Cliente;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class ExportacionExcelTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Principio I + IV: test de aislamiento multi-tenant, escrito antes de la implementación.
     */
    public function test_no_exporta_filas_de_otro_tenant_aunque_se_manden_sus_ids(): void
    {
        Excel::fake();

        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $userA = User::factory()->create(['tenant_id' => $tenantA->id, 'password' => bcrypt('secret123')]);

        $clienteA = Cliente::factory()->create(['tenant_id' => $tenantA->id]);
        $clienteB = Cliente::factory()->create(['tenant_id' => $tenantB->id]);

        $this->loginAs($userA);

        $response = $this->postJson('/exportar/clientes', ['ids' => [$clienteA->id, $clienteB->id]]);

        $response->assertOk();

        Excel::assertDownloaded(
            'clientes-'.now()->format('Y-m-d').'.xlsx',
            function (ExportacionModulo $export) use ($clienteA) {
                return $export->query()->pluck('id')->all() === [$clienteA->id];
            }
        );
    }

    public function test_exporta_solo_los_ids_recibidos(): void
    {
        Excel::fake();

        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);

        $clientes = Cliente::factory()->count(5)->create(['tenant_id' => $tenant->id]);
        $incluidos = $clientes->take(2);

        $this->loginAs($user);

        $response = $this->postJson('/exportar/clientes', ['ids' => $incluidos->pluck('id')->all()]);

        $response->assertOk();

        Excel::assertDownloaded(
            'clientes-'.now()->format('Y-m-d').'.xlsx',
            function (ExportacionModulo $export) use ($incluidos) {
                $ids = $export->query()->pluck('id')->sort()->values()->all();
                $esperados = $incluidos->pluck('id')->sort()->values()->all();

                return $ids === $esperados;
            }
        );
    }

    public function test_registra_actividad_con_las_filas_realmente_exportadas(): void
    {
        Excel::fake();

        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $userA = User::factory()->create(['tenant_id' => $tenantA->id, 'password' => bcrypt('secret123')]);
        $clienteA = Cliente::factory()->create(['tenant_id' => $tenantA->id]);
        $clienteB = Cliente::factory()->create(['tenant_id' => $tenantB->id]);

        $this->loginAs($userA);

        $this->postJson('/exportar/clientes', ['ids' => [$clienteA->id, $clienteB->id]])->assertOk();

        $this->assertDatabaseHas('logs_actividad', [
            'tenant_id' => $tenantA->id,
            'accion' => 'exportacion',
            'descripcion' => 'Exportó 1 clientes a Excel',
        ]);
    }

    public function test_listado_sin_filas_visibles_devuelve_200(): void
    {
        Excel::fake();

        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $otroTenant = Tenant::factory()->create();
        $clienteOtroTenant = Cliente::factory()->create(['tenant_id' => $otroTenant->id]);

        $this->loginAs($user);

        $response = $this->postJson('/exportar/clientes', ['ids' => [$clienteOtroTenant->id]]);

        $response->assertOk();
    }

    public function test_exportar_requiere_ids(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $this->loginAs($user);

        $this->postJson('/exportar/clientes', [])->assertStatus(422);
    }

    public function test_modulo_no_registrado_devuelve_404(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $this->loginAs($user);

        $this->postJson('/exportar/proveedores', ['ids' => [1]])->assertStatus(404);
    }
}
