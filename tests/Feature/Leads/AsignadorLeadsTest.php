<?php

namespace Tests\Feature\Leads;

use App\Models\Configuracion;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AsignadorLeads;
use App\Support\ConfigCrm;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AsignadorLeadsTest extends TestCase
{
    use RefreshDatabase;

    private function configurarRoundRobin(Tenant $tenant, array $comercialIds): void
    {
        Configuracion::factory()->for($tenant, 'tenant')->create([
            'clave' => ConfigCrm::CLAVE_ASIGNACION_ESTRATEGIA,
            'valor' => 'round_robin',
        ]);
        Configuracion::factory()->for($tenant, 'tenant')->create([
            'clave' => ConfigCrm::CLAVE_ASIGNACION_COMERCIALES,
            'valor' => json_encode($comercialIds),
        ]);
    }

    public function test_round_robin_reparte_equitativamente_entre_comerciales(): void
    {
        $tenant = Tenant::factory()->create();
        $comerciales = User::factory()->count(3)->create(['tenant_id' => $tenant->id]);
        $this->configurarRoundRobin($tenant, $comerciales->pluck('id')->all());

        $asignador = new AsignadorLeads;

        $asignaciones = [];
        for ($i = 0; $i < 9; $i++) {
            $asignaciones[] = $asignador->asignar($tenant->id);
        }

        $conteos = array_count_values($asignaciones);
        $this->assertCount(3, $conteos);
        foreach ($conteos as $conteo) {
            $this->assertEquals(3, $conteo);
        }
    }

    public function test_sin_comerciales_configurados_devuelve_null_bandeja_sin_asignar(): void
    {
        $tenant = Tenant::factory()->create();
        $this->configurarRoundRobin($tenant, []);

        $asignador = new AsignadorLeads;

        $this->assertNull($asignador->asignar($tenant->id));
    }

    public function test_estrategia_manual_no_asigna_automaticamente(): void
    {
        $tenant = Tenant::factory()->create();
        $comercial = User::factory()->create(['tenant_id' => $tenant->id]);
        Configuracion::factory()->for($tenant, 'tenant')->create([
            'clave' => ConfigCrm::CLAVE_ASIGNACION_ESTRATEGIA,
            'valor' => 'manual',
        ]);
        Configuracion::factory()->for($tenant, 'tenant')->create([
            'clave' => ConfigCrm::CLAVE_ASIGNACION_COMERCIALES,
            'valor' => json_encode([$comercial->id]),
        ]);

        $asignador = new AsignadorLeads;

        $this->assertNull($asignador->asignar($tenant->id));
    }
}
