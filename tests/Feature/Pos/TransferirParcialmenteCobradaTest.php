<?php

namespace Tests\Feature\Pos;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MontaSalaPos;
use Tests\TestCase;

/**
 * FR-032: transferir o unir una cuenta parcialmente cobrada mueve **solo lo pendiente**. Lo ya
 * emitido es un documento inmutable que se queda donde se cobró.
 */
class TransferirParcialmenteCobradaTest extends TestCase
{
    use MontaSalaPos, RefreshDatabase;

    public function test_transferir_una_cuenta_parcialmente_cobrada_conserva_el_pendiente(): void
    {
        $this->montarSala(['cobro_dividido_activo' => true], mesas: 2);
        $articulo = $this->articuloPos(10.00, 10);

        $cuenta = $this->abrirCuentaCon([['articulo' => $articulo, 'cantidad' => 3]], $this->mesasPos[1]);
        $lineaId = $cuenta['lineas'][0]['id'];

        $this->postJson("/pos/cuentas/{$cuenta['id']}/cobrar", [
            'version' => $cuenta['version'],
            'lineas' => [['cuenta_linea_id' => $lineaId, 'cantidad' => 1]],
        ])->assertCreated();

        $this->postJson("/pos/cuentas/{$cuenta['id']}/transferir", ['mesa_id' => $this->mesasPos[2]->id])
            ->assertOk()
            ->assertJsonPath('cuenta.pendiente', '22.00'); // 2 unidades × 10 € + 10%
    }

    public function test_unir_una_cuenta_parcialmente_cobrada_solo_mueve_lo_pendiente(): void
    {
        $this->montarSala(['cobro_dividido_activo' => true], mesas: 2);
        $cafe = $this->articuloPos(10.00, 10, ['nombre' => 'Café']);
        $tapa = $this->articuloPos(5.00, 10, ['nombre' => 'Tapa']);

        $origen = $this->abrirCuentaCon([['articulo' => $cafe, 'cantidad' => 3]], $this->mesasPos[1]);
        $destino = $this->abrirCuentaCon([['articulo' => $tapa]], $this->mesasPos[2]);

        $lineaId = $origen['lineas'][0]['id'];
        $this->postJson("/pos/cuentas/{$origen['id']}/cobrar", [
            'version' => $origen['version'],
            'lineas' => [['cuenta_linea_id' => $lineaId, 'cantidad' => 1]],
        ])->assertCreated();

        $respuesta = $this->postJson("/pos/cuentas/{$origen['id']}/unir", ['cuenta_destino_id' => $destino['id']])
            ->assertOk()
            ->json('cuenta');

        // Destino: la tapa (5€) + 2 cafés pendientes (20€) = 25€ + 10% = 27,50 €. El café ya
        // cobrado (1 unidad) NO viaja: sigue en la factura ya emitida.
        $this->assertSame('27.50', $respuesta['pendiente']);
        $this->assertDatabaseCount('facturas', 1);
    }
}
