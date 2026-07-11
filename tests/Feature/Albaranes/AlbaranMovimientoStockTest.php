<?php

namespace Tests\Feature\Albaranes;

use App\Enums\OrigenMovimientoStock;
use App\Enums\TipoMovimientoStock;
use App\Models\Albaran;
use App\Models\AlbaranLinea;
use App\Models\Articulo;
use App\Models\Cliente;
use App\Models\MovimientoStock;
use App\Models\Tenant;
use App\Services\EntregadorAlbaran;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AlbaranMovimientoStockTest extends TestCase
{
    use RefreshDatabase;

    public function test_confirmar_como_entregado_genera_movimiento_de_salida_exacto_trazado_al_albaran(): void
    {
        $tenant = Tenant::factory()->create();
        $cliente = Cliente::factory()->create(['tenant_id' => $tenant->id]);
        $articuloConStock = Articulo::factory()->producto()->create([
            'tenant_id' => $tenant->id,
            'gestion_stock' => true,
            'stock_actual' => 50,
        ]);
        $servicio = Articulo::factory()->servicio()->create(['tenant_id' => $tenant->id]);

        $albaran = Albaran::factory()->create(['tenant_id' => $tenant->id, 'cliente_id' => $cliente->id]);
        AlbaranLinea::factory()->create([
            'tenant_id' => $tenant->id,
            'albaran_id' => $albaran->id,
            'articulo_id' => $articuloConStock->id,
            'cantidad' => 12,
        ]);
        AlbaranLinea::factory()->create([
            'tenant_id' => $tenant->id,
            'albaran_id' => $albaran->id,
            'articulo_id' => $servicio->id,
            'cantidad' => 3,
        ]);

        app(EntregadorAlbaran::class)->entregar($albaran);

        $articuloConStock->refresh();
        $this->assertEquals(38.0, (float) $articuloConStock->stock_actual);

        $movimiento = MovimientoStock::where('albaran_id', $albaran->id)->sole();
        $this->assertEquals($articuloConStock->id, $movimiento->articulo_id);
        $this->assertEquals(TipoMovimientoStock::Salida, $movimiento->tipo);
        $this->assertEquals(OrigenMovimientoStock::Albaran, $movimiento->origen);
        $this->assertEquals(12.0, (float) $movimiento->cantidad);

        $this->assertEquals(0, MovimientoStock::where('articulo_id', $servicio->id)->count());
    }
}
