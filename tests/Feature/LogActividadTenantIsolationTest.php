<?php

namespace Tests\Feature;

use App\Models\LogActividad;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LogActividadTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private function autenticarSinGenerarLog(User $user): void
    {
        $tenant = $user->tenant()->first();
        $this->actingOnDomain($this->domainFor($tenant));
        $this->actingAs($user);
    }

    private function datatableParams(array $overrides = []): array
    {
        return array_merge([
            'draw' => 1,
            'start' => 0,
            'length' => 10,
            'search' => ['value' => ''],
            'order' => [['column' => 0, 'dir' => 'desc']],
            'columns' => [
                ['data' => 'fecha'],
                ['data' => 'usuario_nombre'],
                ['data' => 'accion'],
                ['data' => 'descripcion'],
            ],
        ], $overrides);
    }

    public function test_usuario_del_tenant_a_solo_ve_eventos_del_tenant_a(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $userA = User::factory()->create(['tenant_id' => $tenantA->id]);

        LogActividad::factory()->for($tenantA)->count(3)->create();
        LogActividad::factory()->for($tenantB)->count(5)->create();

        $this->autenticarSinGenerarLog($userA);

        $response = $this->getJson('/logs?'.http_build_query($this->datatableParams()));

        $response->assertOk();
        $response->assertJson(['recordsTotal' => 3, 'recordsFiltered' => 3]);
        $this->assertCount(3, $response->json('data'));

        $tenantBIds = LogActividad::withoutGlobalScopes()->where('tenant_id', $tenantB->id)->pluck('descripcion')->all();
        foreach ($response->json('data') as $fila) {
            $this->assertNotContains($fila['descripcion'], $tenantBIds);
        }
    }

    public function test_aislamiento_se_mantiene_variando_paginacion_busqueda_y_orden(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $userA = User::factory()->create(['tenant_id' => $tenantA->id]);

        LogActividad::factory()->for($tenantA)->count(4)->create(['usuario_nombre' => 'Usuario Tenant A']);
        LogActividad::factory()->for($tenantB)->count(20)->create(['usuario_nombre' => 'Usuario Tenant A']);

        $this->autenticarSinGenerarLog($userA);

        $variaciones = [
            ['start' => 0, 'length' => 2],
            ['start' => 2, 'length' => 2],
            ['search' => ['value' => 'Usuario Tenant A']],
            ['order' => [['column' => 1, 'dir' => 'asc']]],
            ['order' => [['column' => 2, 'dir' => 'desc']]],
        ];

        foreach ($variaciones as $params) {
            $response = $this->getJson('/logs?'.http_build_query($this->datatableParams($params)));

            $response->assertOk();
            $this->assertSame(4, $response->json('recordsTotal'), 'recordsTotal no debe incluir filas del tenant B: '.json_encode($params));
            $this->assertLessThanOrEqual(4, $response->json('recordsFiltered'));
        }
    }

    public function test_no_puede_ver_eventos_de_otro_tenant_aunque_el_tenant_propio_este_vacio(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $userA = User::factory()->create(['tenant_id' => $tenantA->id]);

        LogActividad::factory()->for($tenantB)->count(10)->create();

        $this->autenticarSinGenerarLog($userA);

        $response = $this->getJson('/logs?'.http_build_query($this->datatableParams()));

        $response->assertOk();
        $response->assertJson(['recordsTotal' => 0, 'recordsFiltered' => 0, 'data' => []]);
    }
}
