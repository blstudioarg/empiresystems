<?php

namespace Tests\Feature;

use App\Enums\OrigenMovimientoStock;
use App\Enums\TipoArticulo;
use App\Enums\TipoMovimientoStock;
use App\Exceptions\MovimientoStockInvalidoException;
use App\Models\Articulo;
use App\Models\Tenant;
use App\Models\User;
use App\Services\RegistroMovimientoStock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MovimientoStockTest extends TestCase
{
    use RefreshDatabase;

    public function test_resultante_encadenado_tras_varios_movimientos(): void
    {
        $tenant = Tenant::factory()->create();
        $articulo = Articulo::factory()->for($tenant)->create([
            'tipo' => TipoArticulo::Producto,
            'gestion_stock' => true,
            'stock_actual' => 10,
        ]);

        $registro = app(RegistroMovimientoStock::class);

        $m1 = $registro->registrar($articulo, TipoMovimientoStock::Entrada, 5, OrigenMovimientoStock::AjusteManual, 'inventario');
        $this->assertEquals(15, (float) $m1->stock_resultante);
        $this->assertEquals(15, (float) $articulo->refresh()->stock_actual);

        $m2 = $registro->registrar($articulo, TipoMovimientoStock::Salida, 3, OrigenMovimientoStock::AjusteManual, 'rotura');
        $this->assertEquals(12, (float) $m2->stock_resultante);
        $this->assertEquals(12, (float) $articulo->refresh()->stock_actual);
    }

    public function test_reconstruccion_por_historico_coincide_con_stock_actual(): void
    {
        $tenant = Tenant::factory()->create();
        $articulo = Articulo::factory()->for($tenant)->create([
            'tipo' => TipoArticulo::Producto,
            'gestion_stock' => true,
            'stock_actual' => 0,
        ]);

        $registro = app(RegistroMovimientoStock::class);
        $registro->registrar($articulo, TipoMovimientoStock::Entrada, 20, OrigenMovimientoStock::AjusteManual, 'inicial');
        $registro->registrar($articulo, TipoMovimientoStock::Salida, 7, OrigenMovimientoStock::AjusteManual, 'venta');
        $registro->registrar($articulo, TipoMovimientoStock::Entrada, 2, OrigenMovimientoStock::AjusteManual, 'devolucion');

        $suma = $articulo->movimientos->reduce(function (float $acumulado, $movimiento) {
            $signo = $movimiento->tipo === TipoMovimientoStock::Salida ? -1 : 1;

            return $acumulado + $signo * (float) $movimiento->cantidad;
        }, 0.0);

        $this->assertEquals($suma, (float) $articulo->refresh()->stock_actual);
        $this->assertEquals(15, (float) $articulo->stock_actual);
    }

    public function test_movimientos_son_append_only_sin_rutas_de_edicion_o_borrado(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $this->loginAs($user);

        $this->assertFalse(\Illuminate\Support\Facades\Route::has('stock.update'));
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('stock.destroy'));
    }

    public function test_rechaza_movimiento_sobre_servicio(): void
    {
        $tenant = Tenant::factory()->create();
        $articulo = Articulo::factory()->for($tenant)->create([
            'tipo' => TipoArticulo::Servicio,
            'gestion_stock' => false,
            'stock_actual' => null,
        ]);

        $this->expectException(MovimientoStockInvalidoException::class);

        app(RegistroMovimientoStock::class)->registrar(
            $articulo, TipoMovimientoStock::Entrada, 1, OrigenMovimientoStock::AjusteManual, 'motivo'
        );
    }

    public function test_rechaza_movimiento_sobre_producto_sin_gestion_de_stock(): void
    {
        $tenant = Tenant::factory()->create();
        $articulo = Articulo::factory()->for($tenant)->create([
            'tipo' => TipoArticulo::Producto,
            'gestion_stock' => false,
            'stock_actual' => null,
        ]);

        $this->expectException(MovimientoStockInvalidoException::class);

        app(RegistroMovimientoStock::class)->registrar(
            $articulo, TipoMovimientoStock::Entrada, 1, OrigenMovimientoStock::AjusteManual, 'motivo'
        );
    }

    public function test_ajuste_manual_via_http(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $articulo = Articulo::factory()->for($tenant)->create([
            'tipo' => TipoArticulo::Producto,
            'gestion_stock' => true,
            'stock_actual' => 10,
        ]);
        $this->loginAs($user);

        $response = $this->post('/stock/ajuste', [
            'articulo_id' => $articulo->id,
            'tipo' => 'entrada',
            'cantidad' => 5,
            'motivo' => 'recuento',
        ]);

        $response->assertRedirect(route('stock.index'));
        $this->assertDatabaseHas('movimientos_stock', [
            'articulo_id' => $articulo->id,
            'tenant_id' => $tenant->id,
            'stock_resultante' => 15,
        ]);
    }

    public function test_aislamiento_entre_tenants_en_movimientos(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $articuloA = Articulo::factory()->for($tenantA)->create([
            'tipo' => TipoArticulo::Producto,
            'gestion_stock' => true,
            'stock_actual' => 5,
        ]);
        $articuloB = Articulo::factory()->for($tenantB)->create([
            'tipo' => TipoArticulo::Producto,
            'gestion_stock' => true,
            'stock_actual' => 5,
        ]);

        $registro = app(RegistroMovimientoStock::class);
        $registro->registrar($articuloA, TipoMovimientoStock::Entrada, 1, OrigenMovimientoStock::AjusteManual, 'a');
        $registro->registrar($articuloB, TipoMovimientoStock::Entrada, 2, OrigenMovimientoStock::AjusteManual, 'b');

        $userA = User::factory()->create(['tenant_id' => $tenantA->id, 'password' => bcrypt('secret123')]);
        $this->loginAs($userA);

        $response = $this->getJson('/stock');

        $response->assertOk();
        $response->assertJsonFragment(['motivo' => 'a']);
        $response->assertJsonMissing(['motivo' => 'b']);
    }
}
