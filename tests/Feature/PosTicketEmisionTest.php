<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Factura;
use App\Models\FacturaEvento;
use App\Models\Serie;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PosTicketEmisionTest extends TestCase
{
    use RefreshDatabase;

    private function prepararTenant(): array
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        Serie::factory()->simplificada()->for($tenant, 'tenant')->create();

        return [$tenant, $user];
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'lineas' => [
                ['concepto' => 'Café', 'cantidad' => 2, 'precio_unitario' => 1.50, 'tipo_impositivo' => 10],
            ],
        ], $overrides);
    }

    public function test_ticket_simple_se_emite_con_numero_de_serie_s_y_sin_receptor(): void
    {
        [$tenant, $user] = $this->prepararTenant();
        $this->loginAs($user);

        $response = $this->postJson('/pos', $this->payload());

        $response->assertCreated();
        $anio = now()->year;
        $response->assertJson(['numero_completo' => "S-{$anio}-0001"]);

        $ticket = Factura::firstWhere('numero_completo', "S-{$anio}-0001");
        $this->assertEquals('simplificada', $ticket->tipo->value);
        $this->assertEquals('emitida', $ticket->estado->value);
        $this->assertNull($ticket->cliente_id);
        $this->assertNull($ticket->cliente_nif);
        $this->assertEquals(3.30, (float) $ticket->total);
    }

    public function test_dos_tickets_reciben_numeros_consecutivos_sin_huecos(): void
    {
        [$tenant, $user] = $this->prepararTenant();
        $this->loginAs($user);

        $this->postJson('/pos', $this->payload())->assertCreated();
        $this->postJson('/pos', $this->payload())->assertCreated();

        $anio = now()->year;
        $this->assertNotNull(Factura::firstWhere('numero_completo', "S-{$anio}-0001"));
        $this->assertNotNull(Factura::firstWhere('numero_completo', "S-{$anio}-0002"));
    }

    public function test_emitir_ticket_registra_un_evento_emitida(): void
    {
        [$tenant, $user] = $this->prepararTenant();
        $this->loginAs($user);

        $response = $this->postJson('/pos', $this->payload());
        $ticketId = $response->json('id');

        $eventos = FacturaEvento::where('factura_id', $ticketId)->where('tipo_evento', 'emitida')->get();
        $this->assertCount(1, $eventos);
    }

    public function test_ticket_cualificado_persiste_receptor_y_sigue_siendo_simplificada(): void
    {
        [$tenant, $user] = $this->prepararTenant();
        $cliente = Cliente::factory()->create([
            'tenant_id' => $tenant->id,
            'nif' => '12345678Z',
            'nombre' => 'María López',
            'direccion' => 'Calle Real 1',
        ]);
        $this->loginAs($user);

        $response = $this->postJson('/pos', $this->payload([
            'receptor' => [
                'cliente_id' => $cliente->id,
                'cliente_nombre' => 'María López',
                'cliente_nif' => '12345678Z',
                'cliente_direccion' => 'Calle Real 1',
            ],
        ]));

        $response->assertCreated();
        $ticket = Factura::firstWhere('id', $response->json('id'));
        $this->assertEquals('simplificada', $ticket->tipo->value);
        $this->assertEquals('12345678Z', $ticket->cliente_nif);
        $this->assertEquals($cliente->id, $ticket->cliente_id);
    }

    public function test_ticket_emitido_no_se_puede_eliminar_via_facturas(): void
    {
        [$tenant, $user] = $this->prepararTenant();
        $this->loginAs($user);

        $ticketId = $this->postJson('/pos', $this->payload())->json('id');

        // El módulo Facturas sólo permite borrar borradores; un ticket emitido no es borrable.
        $this->deleteJson("/facturas/{$ticketId}")->assertStatus(403);
        $this->assertNotNull(Factura::find($ticketId));
    }
}
