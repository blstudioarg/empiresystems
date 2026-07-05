<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Factura;
use App\Models\Serie;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PosListadoSeparadoTest extends TestCase
{
    use RefreshDatabase;

    public function test_pos_index_solo_muestra_simplificadas_y_facturas_index_las_excluye(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        Serie::factory()->simplificada()->for($tenant, 'tenant')->create();

        $cliente = Cliente::factory()->create(['tenant_id' => $tenant->id]);

        // Una factura ordinaria emitida.
        $serieOrdinaria = Serie::factory()->for($tenant, 'tenant')->create();
        Factura::factory()->emitida()->create([
            'tenant_id' => $tenant->id,
            'serie_id' => $serieOrdinaria->id,
            'cliente_id' => $cliente->id,
            'cliente_nombre' => 'Cliente',
            'cliente_nif' => '12345678Z',
            'cliente_direccion' => 'Calle 1',
        ]);

        $this->loginAs($user);

        // Emite un ticket simplificado.
        $this->postJson('/pos', [
            'lineas' => [['concepto' => 'Café', 'cantidad' => 1, 'precio_unitario' => 2, 'tipo_impositivo' => 10]],
        ])->assertCreated();

        $posData = collect($this->getJson('/pos')->assertOk()->json('data'));
        $this->assertCount(1, $posData);
        $this->assertStringStartsWith('S-', $posData->first()['identificador']);

        $facturasData = collect($this->getJson('/facturas')->assertOk()->json('data'));
        $this->assertCount(1, $facturasData);
        $this->assertStringStartsWith('F-', $facturasData->first()['identificador']);
    }
}
