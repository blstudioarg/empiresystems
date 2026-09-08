<?php

namespace Tests\Feature;

use App\Models\Factura;
use App\Models\Serie;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PosPagoDivididoTest extends TestCase
{
    use RefreshDatabase;

    private function prepararTenant(): array
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        Serie::factory()->simplificada()->for($tenant, 'tenant')->create();

        return [$tenant, $user];
    }

    /**
     * @param  array<int, array<string, mixed>>  $pagos
     */
    private function payload(?array $pagos = null): array
    {
        // Café x2 a 1,50 + 10% IVA = 3,30 €.
        $data = [
            'lineas' => [
                ['concepto' => 'Café', 'cantidad' => 2, 'precio_unitario' => 1.50, 'tipo_impositivo' => 10],
            ],
        ];

        if ($pagos !== null) {
            $data['pagos'] = $pagos;
        }

        return $data;
    }

    public function test_ticket_sin_pagos_registra_un_unico_pago_en_efectivo_por_el_total(): void
    {
        [$tenant, $user] = $this->prepararTenant();
        $this->loginAs($user);

        $id = $this->postJson('/pos', $this->payload())->assertCreated()->json('id');

        $ticket = Factura::with('pagosTicket')->find($id);
        $this->assertCount(1, $ticket->pagosTicket);
        $this->assertEquals('efectivo', $ticket->pagosTicket->first()->metodo->value);
        $this->assertEquals(3.30, (float) $ticket->pagosTicket->first()->importe);
        $this->assertEquals('efectivo', $ticket->forma_pago->value);
    }

    public function test_pago_dividido_persiste_cada_metodo_con_su_importe(): void
    {
        [$tenant, $user] = $this->prepararTenant();
        $this->loginAs($user);

        $id = $this->postJson('/pos', $this->payload([
            ['metodo' => 'efectivo', 'importe' => 2.00],
            ['metodo' => 'tarjeta', 'importe' => 1.30],
        ]))->assertCreated()->json('id');

        $ticket = Factura::with('pagosTicket')->find($id);
        $this->assertCount(2, $ticket->pagosTicket);
        $this->assertEqualsCanonicalizing(
            ['efectivo', 'tarjeta'],
            $ticket->pagosTicket->pluck('metodo')->map->value->all(),
        );
        // forma_pago = método predominante (mayor importe): efectivo (2,00 > 1,30).
        $this->assertEquals('efectivo', $ticket->forma_pago->value);
    }

    public function test_reparto_que_no_cuadra_con_el_total_es_rechazado(): void
    {
        [$tenant, $user] = $this->prepararTenant();
        $this->loginAs($user);

        $this->postJson('/pos', $this->payload([
            ['metodo' => 'efectivo', 'importe' => 2.00],
            ['metodo' => 'tarjeta', 'importe' => 1.00], // suma 3,00 ≠ 3,30
        ]))->assertStatus(422);

        $this->assertDatabaseCount('ticket_pagos', 0);
        $this->assertDatabaseCount('facturas', 0);
    }

    public function test_metodo_de_pago_invalido_es_rechazado_por_validacion(): void
    {
        [$tenant, $user] = $this->prepararTenant();
        $this->loginAs($user);

        $this->postJson('/pos', $this->payload([
            ['metodo' => 'bitcoin', 'importe' => 3.30],
        ]))->assertStatus(422)->assertJsonValidationErrors('pagos.0.metodo');
    }

    public function test_el_desglose_aparece_en_el_listado_json_de_tickets(): void
    {
        [$tenant, $user] = $this->prepararTenant();
        $this->loginAs($user);

        $this->postJson('/pos', $this->payload([
            ['metodo' => 'efectivo', 'importe' => 2.00],
            ['metodo' => 'tarjeta', 'importe' => 1.30],
        ]))->assertCreated();

        $response = $this->getJson('/pos');
        $response->assertOk();
        $fila = $response->json('data.0');

        $this->assertTrue($fila['dividido']);
        $this->assertCount(2, $fila['pagos']);
    }

    public function test_el_desglose_de_un_ticket_no_se_ve_desde_otro_tenant(): void
    {
        [$tenantA, $userA] = $this->prepararTenant();
        $this->loginAs($userA);
        $this->postJson('/pos', $this->payload([
            ['metodo' => 'tarjeta', 'importe' => 3.30],
        ]))->assertCreated();

        [$tenantB, $userB] = $this->prepararTenant();
        $this->loginAs($userB);

        $this->assertEquals(0, $this->getJson('/pos')->assertOk()->json('totales.total'));
    }
}
