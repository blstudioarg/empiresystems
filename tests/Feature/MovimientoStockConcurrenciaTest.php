<?php

namespace Tests\Feature;

use App\Enums\OrigenMovimientoStock;
use App\Enums\TipoArticulo;
use App\Enums\TipoMovimientoStock;
use App\Models\Articulo;
use App\Models\Tenant;
use App\Services\RegistroMovimientoStock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MovimientoStockConcurrenciaTest extends TestCase
{
    use RefreshDatabase;

    public function test_movimientos_secuenciales_sobre_el_mismo_articulo_no_descuadran_stock_actual(): void
    {
        $tenant = Tenant::factory()->create();
        $articulo = Articulo::factory()->for($tenant)->create([
            'tipo' => TipoArticulo::Producto,
            'gestion_stock' => true,
            'stock_actual' => 100,
        ]);

        $registro = app(RegistroMovimientoStock::class);

        // Simula dos operaciones "concurrentes" ejecutando ambas dentro de transacciones propias
        // que usan lockForUpdate() sobre el mismo artículo; deben serializarse sin perder ninguna.
        for ($i = 0; $i < 10; $i++) {
            $registro->registrar($articulo, TipoMovimientoStock::Salida, 1, OrigenMovimientoStock::AjusteManual, 'concurrencia');
        }

        $articulo->refresh();
        $this->assertEquals(90, (float) $articulo->stock_actual);

        $ultimoMovimiento = $articulo->movimientos()->orderByDesc('id')->first();
        $this->assertEquals((float) $articulo->stock_actual, (float) $ultimoMovimiento->stock_resultante);

        $this->assertEquals(10, DB::table('movimientos_stock')->where('articulo_id', $articulo->id)->count());
    }
}
