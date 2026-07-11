<?php

namespace Tests\Feature\Albaranes;

use App\Enums\EstadoAlbaran;
use App\Enums\EstadoFactura;
use App\Enums\RegimenImpositivo;
use App\Exceptions\ConversionAlbaranesException;
use App\Models\Albaran;
use App\Models\AlbaranLinea;
use App\Models\Articulo;
use App\Models\Cliente;
use App\Models\Tenant;
use App\Services\ConversorAlbaranesFactura;
use App\Services\EmisorFacturas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConversorAlbaranesFacturaTest extends TestCase
{
    use RefreshDatabase;

    public function test_convertir_n_albaranes_del_mismo_cliente_crea_una_factura_con_la_suma_exacta_sin_mover_stock_de_nuevo(): void
    {
        $tenant = Tenant::factory()->create();
        $cliente = Cliente::factory()->create(['tenant_id' => $tenant->id]);
        $articulo = Articulo::factory()->producto()->create([
            'tenant_id' => $tenant->id,
            'gestion_stock' => true,
            'stock_actual' => 100,
        ]);

        $albaranes = collect();
        foreach ([10, 15, 20] as $cantidad) {
            $albaran = Albaran::factory()->entregado()->create([
                'tenant_id' => $tenant->id,
                'cliente_id' => $cliente->id,
                'regimen_impositivo' => RegimenImpositivo::Iva,
                'receptor_nif' => 'B12345678',
                'base_total' => $cantidad * 10,
                'cuota_impuesto_total' => round($cantidad * 10 * 0.21, 2),
                'total' => round($cantidad * 10 * 1.21, 2),
            ]);
            AlbaranLinea::factory()->create([
                'tenant_id' => $tenant->id,
                'albaran_id' => $albaran->id,
                'articulo_id' => $articulo->id,
                'cantidad' => $cantidad,
                'precio_unitario' => 10,
                'base' => $cantidad * 10,
                'tipo_impositivo' => 21,
                'cuota_impuesto' => round($cantidad * 10 * 0.21, 2),
            ]);
            $albaranes->push($albaran);
        }

        $stockAntes = $articulo->refresh()->stock_actual;

        $factura = app(ConversorAlbaranesFactura::class)->convertir($albaranes);

        $this->assertEquals(EstadoFactura::Borrador, $factura->estado);
        $this->assertEquals(3, $factura->lineas()->count());
        $this->assertEquals(450.0, (float) $factura->base_total);
        $this->assertEquals(round(450 * 1.21, 2), (float) $factura->total);

        foreach ($albaranes as $albaran) {
            $albaran->refresh();
            $this->assertEquals(EstadoAlbaran::Facturado, $albaran->estado);
            $this->assertEquals($factura->id, $albaran->convertido_a_factura_id);
        }

        $this->assertEquals((float) $stockAntes, (float) $articulo->refresh()->stock_actual);

        // Emitir la factura consolidada no debe generar movimiento de stock adicional (FR-010).
        app(EmisorFacturas::class)->emitir($factura->fresh());
        $this->assertEquals((float) $stockAntes, (float) $articulo->refresh()->stock_actual);
    }

    public function test_rechaza_convertir_albaranes_de_distinto_cliente(): void
    {
        $tenant = Tenant::factory()->create();
        $clienteA = Cliente::factory()->create(['tenant_id' => $tenant->id]);
        $clienteB = Cliente::factory()->create(['tenant_id' => $tenant->id]);

        $albaranA = Albaran::factory()->entregado()->create(['tenant_id' => $tenant->id, 'cliente_id' => $clienteA->id]);
        $albaranB = Albaran::factory()->entregado()->create(['tenant_id' => $tenant->id, 'cliente_id' => $clienteB->id]);

        $this->expectException(ConversionAlbaranesException::class);

        app(ConversorAlbaranesFactura::class)->convertir(collect([$albaranA, $albaranB]));
    }

    public function test_rechaza_convertir_un_albaran_ya_facturado(): void
    {
        $tenant = Tenant::factory()->create();
        $cliente = Cliente::factory()->create(['tenant_id' => $tenant->id]);

        $albaranFacturado = Albaran::factory()->facturado()->create(['tenant_id' => $tenant->id, 'cliente_id' => $cliente->id]);

        $this->expectException(ConversionAlbaranesException::class);

        app(ConversorAlbaranesFactura::class)->convertir(collect([$albaranFacturado]));
    }
}
