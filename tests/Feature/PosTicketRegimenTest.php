<?php

namespace Tests\Feature;

use App\Models\Factura;
use App\Models\FacturaImpuesto;
use App\Models\Serie;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PosTicketRegimenTest extends TestCase
{
    use RefreshDatabase;

    private function prepararTenant(string $regimen): array
    {
        $tenant = Tenant::factory()->create(['regimen_impositivo' => $regimen]);
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        Serie::factory()->simplificada()->for($tenant, 'tenant')->create();

        return [$tenant, $user];
    }

    public function test_ticket_emitido_en_tenant_igic_usa_desglose_igic(): void
    {
        [$tenant, $user] = $this->prepararTenant('igic');
        $this->loginAs($user);

        $response = $this->postJson('/pos', [
            'lineas' => [
                ['concepto' => 'Café', 'cantidad' => 2, 'precio_unitario' => 1.50, 'tipo_impositivo' => 7],
            ],
        ]);

        $response->assertCreated();

        $impuesto = FacturaImpuesto::where('factura_id', $response->json('id'))->first();
        $this->assertNotNull($impuesto);
        $this->assertEquals('igic', $impuesto->tipo_impuesto->value);
        $this->assertEquals(7, (float) $impuesto->porcentaje);
    }

    public function test_regimen_del_ticket_no_se_mezcla_entre_tenants(): void
    {
        [$tenantIgic, $userIgic] = $this->prepararTenant('igic');
        [$tenantIva] = $this->prepararTenant('iva');

        $this->loginAs($userIgic);

        $response = $this->postJson('/pos', [
            'lineas' => [
                ['concepto' => 'Café', 'cantidad' => 2, 'precio_unitario' => 1.50, 'tipo_impositivo' => 7],
            ],
        ]);

        $ticket = Factura::find($response->json('id'));

        $this->assertEquals('igic', $ticket->regimen_impositivo->value);
        $this->assertNotEquals($tenantIva->regimen_impositivo->value, $ticket->regimen_impositivo->value);
    }
}
