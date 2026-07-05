<?php

namespace Tests\Feature;

use App\Models\Factura;
use App\Models\Serie;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PosTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private function emitirTicket(User $user): void
    {
        $this->loginAs($user);
        $this->postJson('/pos', [
            'lineas' => [['concepto' => 'Café', 'cantidad' => 1, 'precio_unitario' => 2, 'tipo_impositivo' => 10]],
        ])->assertCreated();
    }

    public function test_el_listado_pos_de_un_tenant_no_expone_tickets_de_otro(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $userA = User::factory()->create(['tenant_id' => $tenantA->id, 'password' => bcrypt('secret123')]);
        $userB = User::factory()->create(['tenant_id' => $tenantB->id, 'password' => bcrypt('secret123')]);
        Serie::factory()->simplificada()->for($tenantA, 'tenant')->create();
        Serie::factory()->simplificada()->for($tenantB, 'tenant')->create();

        $this->emitirTicket($userB); // ticket en B

        // Ahora A consulta su listado: no debe ver el de B.
        $this->emitirTicket($userA); // ticket en A (también deja sesión en A)
        $data = collect($this->getJson('/pos')->assertOk()->json('data'));

        $this->assertCount(1, $data);
    }

    public function test_la_numeracion_de_la_serie_s_es_independiente_por_tenant(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $userA = User::factory()->create(['tenant_id' => $tenantA->id, 'password' => bcrypt('secret123')]);
        $userB = User::factory()->create(['tenant_id' => $tenantB->id, 'password' => bcrypt('secret123')]);
        Serie::factory()->simplificada()->for($tenantA, 'tenant')->create();
        Serie::factory()->simplificada()->for($tenantB, 'tenant')->create();

        // Dos tickets en B, uno en A. Cada uno empieza en 1 dentro de su serie.
        $this->emitirTicket($userB);
        $this->emitirTicket($userB);
        $this->emitirTicket($userA);

        $anio = now()->year;
        // Aserciones directas a BD (bypass del TenantScope activo).
        $this->assertDatabaseCount('facturas', 3);
        $this->assertDatabaseHas('facturas', ['tenant_id' => $tenantA->id, 'numero_completo' => "S-{$anio}-0001"]);
        $this->assertDatabaseHas('facturas', ['tenant_id' => $tenantB->id, 'numero_completo' => "S-{$anio}-0001"]);
        $this->assertDatabaseHas('facturas', ['tenant_id' => $tenantB->id, 'numero_completo' => "S-{$anio}-0002"]);
    }
}
