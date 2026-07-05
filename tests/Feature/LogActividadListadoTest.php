<?php

namespace Tests\Feature;

use App\Enums\AccionLogActividad;
use App\Models\LogActividad;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LogActividadListadoTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Autentica sin pasar por /login: evita que el propio inicio de sesión (FR-002) sume una
     * fila de log que ensuciaría los conteos exactos que verifican estos tests de listado.
     */
    private function autenticarSinGenerarLog(User $user): void
    {
        $tenant = $user->tenant()->first();
        $this->actingOnDomain($tenant ? $this->domainFor($tenant) : config('tenancy.central_domains')[0]);
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

    public function test_listado_json_devuelve_eventos_ordenados_por_fecha_descendente_por_defecto(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        $antiguo = LogActividad::factory()->for($tenant)->create(['ocurrido_at' => now()->subDays(3)]);
        $reciente = LogActividad::factory()->for($tenant)->create(['ocurrido_at' => now()->subDay()]);
        $masReciente = LogActividad::factory()->for($tenant)->create(['ocurrido_at' => now()]);

        $this->autenticarSinGenerarLog($user);

        $response = $this->getJson('/logs?'.http_build_query($this->datatableParams()));

        $response->assertOk();
        $response->assertJson(['draw' => 1, 'recordsTotal' => 3, 'recordsFiltered' => 3]);
        $data = $response->json('data');
        $this->assertCount(3, $data);
        $this->assertSame($masReciente->descripcion, $data[0]['descripcion']);
        $this->assertSame($antiguo->descripcion, $data[2]['descripcion']);
    }

    public function test_listado_respeta_paginacion(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        LogActividad::factory()->for($tenant)->count(15)->create();

        $this->autenticarSinGenerarLog($user);

        $response = $this->getJson('/logs?'.http_build_query($this->datatableParams(['start' => 0, 'length' => 5])));

        $response->assertOk();
        $response->assertJson(['recordsTotal' => 15, 'recordsFiltered' => 15]);
        $this->assertCount(5, $response->json('data'));
    }

    public function test_tenant_sin_eventos_devuelve_lista_vacia_sin_error(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        $this->autenticarSinGenerarLog($user);

        $response = $this->getJson('/logs?'.http_build_query($this->datatableParams()));

        $response->assertOk();
        $response->assertJson(['recordsTotal' => 0, 'recordsFiltered' => 0, 'data' => []]);
    }

    public function test_busqueda_filtra_por_usuario_nombre_accion_o_descripcion(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        LogActividad::factory()->for($tenant)->create(['usuario_nombre' => 'Ana Pérez', 'descripcion' => 'Inició sesión', 'accion' => AccionLogActividad::Login]);
        LogActividad::factory()->for($tenant)->create(['usuario_nombre' => 'Luis Gómez', 'descripcion' => 'Creó el cliente Acme', 'accion' => AccionLogActividad::Alta]);

        $this->autenticarSinGenerarLog($user);

        $response = $this->getJson('/logs?'.http_build_query($this->datatableParams(['search' => ['value' => 'Ana']])));

        $response->assertOk();
        $response->assertJson(['recordsTotal' => 2, 'recordsFiltered' => 1]);
        $this->assertSame('Ana Pérez', $response->json('data.0.usuario_nombre'));
    }

    public function test_orden_por_usuario_nombre_asc(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        LogActividad::factory()->for($tenant)->create(['usuario_nombre' => 'Zulema']);
        LogActividad::factory()->for($tenant)->create(['usuario_nombre' => 'Ana']);

        $this->autenticarSinGenerarLog($user);

        $response = $this->getJson('/logs?'.http_build_query($this->datatableParams([
            'order' => [['column' => 1, 'dir' => 'asc']],
        ])));

        $response->assertOk();
        $this->assertSame('Ana', $response->json('data.0.usuario_nombre'));
        $this->assertSame('Zulema', $response->json('data.1.usuario_nombre'));
    }

    public function test_orden_mantiene_la_busqueda_aplicada(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        LogActividad::factory()->for($tenant)->create(['usuario_nombre' => 'Zulema Coincide', 'descripcion' => 'Modificó algo']);
        LogActividad::factory()->for($tenant)->create(['usuario_nombre' => 'Ana Coincide', 'descripcion' => 'Creó algo']);
        LogActividad::factory()->for($tenant)->create(['usuario_nombre' => 'Otro Usuario', 'descripcion' => 'Sin relación']);

        $this->autenticarSinGenerarLog($user);

        $response = $this->getJson('/logs?'.http_build_query($this->datatableParams([
            'search' => ['value' => 'Coincide'],
            'order' => [['column' => 1, 'dir' => 'asc']],
        ])));

        $response->assertOk();
        $response->assertJson(['recordsTotal' => 3, 'recordsFiltered' => 2]);
        $this->assertSame('Ana Coincide', $response->json('data.0.usuario_nombre'));
        $this->assertSame('Zulema Coincide', $response->json('data.1.usuario_nombre'));
    }
}
