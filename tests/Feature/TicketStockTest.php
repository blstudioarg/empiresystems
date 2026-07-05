<?php

namespace Tests\Feature;

use App\Enums\TipoArticulo;
use App\Models\Articulo;
use App\Models\Serie;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketStockTest extends TestCase
{
    use RefreshDatabase;

    public function test_emision_de_ticket_pos_descuenta_stock_igual_que_ordinaria(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        Serie::factory()->simplificada()->for($tenant, 'tenant')->create();
        $articulo = Articulo::factory()->for($tenant)->create([
            'tipo' => TipoArticulo::Producto,
            'gestion_stock' => true,
            'stock_actual' => 20,
        ]);
        $this->loginAs($user);

        $response = $this->postJson('/pos', [
            'lineas' => [
                ['articulo_id' => $articulo->id, 'concepto' => $articulo->nombre, 'cantidad' => 3, 'precio_unitario' => 5, 'tipo_impositivo' => 21],
            ],
        ]);

        $response->assertCreated();
        $this->assertEquals(17, (float) $articulo->refresh()->stock_actual);
        $this->assertDatabaseHas('movimientos_stock', [
            'articulo_id' => $articulo->id,
            'tipo' => 'salida',
            'origen' => 'factura',
        ]);
    }
}
