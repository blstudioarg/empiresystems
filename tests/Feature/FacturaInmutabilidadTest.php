<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Factura;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FacturaInmutabilidadTest extends TestCase
{
    use RefreshDatabase;

    private function facturaEmitida(Tenant $tenant, Cliente $cliente): Factura
    {
        return Factura::factory()->create([
            'tenant_id' => $tenant->id,
            'cliente_id' => $cliente->id,
            'estado' => 'emitida',
            'numero' => 1,
            'numero_completo' => 'F-'.now()->year.'-0001',
        ]);
    }

    public function test_no_se_puede_editar_una_factura_emitida(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $cliente = Cliente::factory()->create(['tenant_id' => $tenant->id]);
        $factura = $this->facturaEmitida($tenant, $cliente);

        $this->loginAs($user);

        $response = $this->get("/facturas/{$factura->id}/editar");

        $response->assertForbidden();
    }

    public function test_no_se_puede_actualizar_una_factura_emitida(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $cliente = Cliente::factory()->create(['tenant_id' => $tenant->id]);
        $factura = $this->facturaEmitida($tenant, $cliente);
        $totalOriginal = $factura->total;

        $this->loginAs($user);

        $response = $this->put("/facturas/{$factura->id}", [
            'cliente_id' => $cliente->id,
            'fecha_expedicion' => now()->toDateString(),
            'forma_pago' => 'transferencia',
            'lineas' => [
                ['concepto' => 'Otro', 'cantidad' => 1, 'precio_unitario' => 999, 'tipo_impositivo' => 21],
            ],
        ]);

        $response->assertForbidden();
        $factura->refresh();
        $this->assertEquals($totalOriginal, $factura->total);
    }

    public function test_no_se_puede_eliminar_una_factura_emitida(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $cliente = Cliente::factory()->create(['tenant_id' => $tenant->id]);
        $factura = $this->facturaEmitida($tenant, $cliente);

        $this->loginAs($user);

        $response = $this->delete("/facturas/{$factura->id}");

        $response->assertForbidden();
        $this->assertNotSoftDeleted($factura);
    }

    public function test_no_se_puede_reemitir_una_factura_ya_emitida(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $cliente = Cliente::factory()->create(['tenant_id' => $tenant->id]);
        $factura = $this->facturaEmitida($tenant, $cliente);
        $numeroOriginal = $factura->numero;

        $this->loginAs($user);

        $response = $this->postJson("/facturas/{$factura->id}/emitir");

        $response->assertStatus(422);
        $factura->refresh();
        $this->assertEquals($numeroOriginal, $factura->numero);
    }
}
