<?php

namespace Tests\Feature\Oportunidades;

use App\Enums\EstadoLead;
use App\Enums\EtapaOportunidad;
use App\Models\Lead;
use App\Models\Oportunidad;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OportunidadPipelineTest extends TestCase
{
    use RefreshDatabase;

    public function test_ganar_una_oportunidad_con_lead_convierte_el_lead_en_cliente(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $lead = Lead::factory()->create(['tenant_id' => $tenant->id, 'estado' => EstadoLead::Cualificado]);
        $oportunidad = Oportunidad::factory()->create([
            'tenant_id' => $tenant->id,
            'lead_id' => $lead->id,
            'cliente_id' => null,
        ]);

        $this->loginAs($user);

        $response = $this->put("/oportunidades/{$oportunidad->id}/etapa", ['etapa' => 'ganada']);

        $response->assertRedirect();
        $oportunidad->refresh();
        $lead->refresh();

        $this->assertEquals(EtapaOportunidad::Ganada, $oportunidad->etapa);
        $this->assertNotNull($oportunidad->cerrada_at);
        $this->assertEquals(EstadoLead::Convertido, $lead->estado);
        $this->assertNotNull($lead->convertido_a_cliente_id);
    }

    public function test_perder_una_oportunidad_exige_motivo(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $oportunidad = Oportunidad::factory()->create(['tenant_id' => $tenant->id]);

        $this->loginAs($user);

        $response = $this->put("/oportunidades/{$oportunidad->id}/etapa", ['etapa' => 'perdida']);

        $response->assertSessionHasErrors('motivo_perdida');
        $this->assertNotEquals(EtapaOportunidad::Perdida, $oportunidad->fresh()->etapa);
    }

    public function test_perder_una_oportunidad_bloquea_nuevos_presupuestos(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $oportunidad = Oportunidad::factory()->perdida()->create(['tenant_id' => $tenant->id]);

        $this->loginAs($user);

        $response = $this->post('/presupuestos', [
            'oportunidad_id' => $oportunidad->id,
            'cliente_id' => $oportunidad->cliente_id,
            'fecha_emision' => now()->toDateString(),
            'lineas' => [
                ['concepto' => 'Servicio', 'cantidad' => 1, 'precio_unitario' => 100, 'tipo_impositivo' => 21],
            ],
        ]);

        $response->assertSessionHasErrors('oportunidad_id');
        $this->assertDatabaseCount('presupuestos', 0);
    }
}
