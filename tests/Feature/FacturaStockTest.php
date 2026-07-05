<?php

namespace Tests\Feature;

use App\Enums\TipoArticulo;
use App\Enums\TipoRectificacion;
use App\Models\Articulo;
use App\Models\Cliente;
use App\Models\Factura;
use App\Models\Serie;
use App\Models\Tenant;
use App\Models\User;
use App\Services\GeneradorRectificativa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FacturaStockTest extends TestCase
{
    use RefreshDatabase;

    private function facturaBorradorConArticulo(Tenant $tenant, Articulo $articulo, float $cantidad = 4): Factura
    {
        $serie = Serie::factory()->create(['tenant_id' => $tenant->id, 'codigo' => 'F', 'formato' => '{serie}-{anio}-{numero:0000}']);
        $cliente = Cliente::factory()->create([
            'tenant_id' => $tenant->id,
            'nif' => '12345678Z',
            'nombre' => 'Cliente de prueba',
            'direccion' => 'Calle Falsa 123',
        ]);

        $factura = Factura::factory()->create([
            'tenant_id' => $tenant->id,
            'serie_id' => $serie->id,
            'cliente_id' => $cliente->id,
            'cliente_nombre' => $cliente->nombre,
            'cliente_nif' => $cliente->nif,
            'cliente_direccion' => $cliente->direccion,
            'estado' => 'borrador',
            'numero' => null,
            'numero_completo' => null,
            'base_total' => 100,
            'total' => 121,
        ]);

        $factura->lineas()->create([
            'tenant_id' => $tenant->id,
            'articulo_id' => $articulo->id,
            'concepto' => $articulo->nombre,
            'cantidad' => $cantidad,
            'precio_unitario' => 25,
            'base' => 100,
            'tipo_impositivo' => 21,
            'cuota_impuesto' => 21,
            'orden' => 0,
        ]);

        return $factura;
    }

    public function test_emitir_factura_descuenta_stock_del_articulo(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $articulo = Articulo::factory()->for($tenant)->create([
            'tipo' => TipoArticulo::Producto,
            'gestion_stock' => true,
            'stock_actual' => 30,
        ]);
        $factura = $this->facturaBorradorConArticulo($tenant, $articulo, 4);
        $this->loginAs($user);

        $response = $this->post("/facturas/{$factura->id}/emitir");

        $response->assertRedirect(route('facturas.index'));
        $this->assertEquals(26, (float) $articulo->refresh()->stock_actual);
        $this->assertDatabaseHas('movimientos_stock', [
            'articulo_id' => $articulo->id,
            'factura_id' => $factura->id,
            'tipo' => 'salida',
            'origen' => 'factura',
        ]);
    }

    public function test_factura_en_borrador_no_descuenta_stock(): void
    {
        $tenant = Tenant::factory()->create();
        $articulo = Articulo::factory()->for($tenant)->create([
            'tipo' => TipoArticulo::Producto,
            'gestion_stock' => true,
            'stock_actual' => 30,
        ]);
        $this->facturaBorradorConArticulo($tenant, $articulo, 4);

        $this->assertEquals(30, (float) $articulo->refresh()->stock_actual);
        $this->assertDatabaseCount('movimientos_stock', 0);
    }

    public function test_rectificativa_al_emitirse_revierte_el_stock(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $articulo = Articulo::factory()->for($tenant)->create([
            'tipo' => TipoArticulo::Producto,
            'gestion_stock' => true,
            'stock_actual' => 30,
        ]);
        $factura = $this->facturaBorradorConArticulo($tenant, $articulo, 4);
        $this->loginAs($user);

        $this->post("/facturas/{$factura->id}/emitir");
        $this->assertEquals(26, (float) $articulo->refresh()->stock_actual);

        $factura->refresh();
        $rectificativa = app(GeneradorRectificativa::class)->generar($factura, TipoRectificacion::Sustitucion, 'anulación');

        $this->post("/facturas/{$rectificativa->id}/emitir");

        $this->assertEquals(30, (float) $articulo->refresh()->stock_actual);
        $this->assertDatabaseHas('movimientos_stock', [
            'articulo_id' => $articulo->id,
            'factura_id' => $rectificativa->id,
            'tipo' => 'entrada',
            'origen' => 'devolucion',
        ]);
    }

    public function test_emitir_con_cantidad_mayor_al_stock_permite_resultante_negativo(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $articulo = Articulo::factory()->for($tenant)->create([
            'tipo' => TipoArticulo::Producto,
            'gestion_stock' => true,
            'stock_actual' => 2,
        ]);
        $factura = $this->facturaBorradorConArticulo($tenant, $articulo, 5);
        $this->loginAs($user);

        $response = $this->post("/facturas/{$factura->id}/emitir");

        $response->assertRedirect(route('facturas.index'));
        $this->assertEquals(-3, (float) $articulo->refresh()->stock_actual);
    }

    public function test_linea_libre_sin_articulo_no_mueve_stock(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $serie = Serie::factory()->create(['tenant_id' => $tenant->id, 'codigo' => 'F', 'formato' => '{serie}-{anio}-{numero:0000}']);
        $cliente = Cliente::factory()->create([
            'tenant_id' => $tenant->id,
            'nif' => '12345678Z',
            'nombre' => 'Cliente de prueba',
            'direccion' => 'Calle Falsa 123',
        ]);
        $factura = Factura::factory()->create([
            'tenant_id' => $tenant->id,
            'serie_id' => $serie->id,
            'cliente_id' => $cliente->id,
            'cliente_nombre' => $cliente->nombre,
            'cliente_nif' => $cliente->nif,
            'cliente_direccion' => $cliente->direccion,
            'estado' => 'borrador',
            'numero' => null,
            'numero_completo' => null,
            'base_total' => 100,
            'total' => 121,
        ]);
        $factura->lineas()->create([
            'tenant_id' => $tenant->id,
            'articulo_id' => null,
            'concepto' => 'Servicio libre',
            'cantidad' => 1,
            'precio_unitario' => 100,
            'base' => 100,
            'tipo_impositivo' => 21,
            'cuota_impuesto' => 21,
            'orden' => 0,
        ]);
        $this->loginAs($user);

        $response = $this->post("/facturas/{$factura->id}/emitir");

        $response->assertRedirect(route('facturas.index'));
        $this->assertDatabaseCount('movimientos_stock', 0);
    }

    public function test_aislamiento_entre_tenants_al_emitir(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $articuloA = Articulo::factory()->for($tenantA)->create([
            'tipo' => TipoArticulo::Producto,
            'gestion_stock' => true,
            'stock_actual' => 10,
        ]);

        $userA = User::factory()->create(['tenant_id' => $tenantA->id, 'password' => bcrypt('secret123')]);
        $facturaA = $this->facturaBorradorConArticulo($tenantA, $articuloA, 1);
        $this->loginAs($userA);

        $this->post("/facturas/{$facturaA->id}/emitir");

        $this->assertEquals(9, (float) $articuloA->refresh()->stock_actual);
        $this->assertDatabaseHas('movimientos_stock', ['tenant_id' => $tenantA->id, 'articulo_id' => $articuloA->id]);
        $this->assertDatabaseMissing('movimientos_stock', ['tenant_id' => $tenantB->id]);
    }
}
