<?php

namespace Tests\Feature\Pos;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MontaSalaPos;
use Tests\TestCase;

/**
 * Edge case del spec: el tope legal de la simplificada se comprueba sobre el importe de **ese**
 * cobro, no sobre el total acumulado de la cuenta.
 */
class CobroParcialTopeTest extends TestCase
{
    use MontaSalaPos, RefreshDatabase;

    public function test_un_cobro_parcial_por_debajo_del_tope_se_emite_aunque_la_cuenta_total_lo_supere(): void
    {
        $this->montarSala(['cobro_dividido_activo' => true]);
        // Tope simplificada por defecto: 400 €. La cuenta completa (10 × 100 € + 10% = 1.100 €)
        // está muy por encima; cada cobro individual de 1 unidad (110 €) no.
        $articulo = $this->articuloPos(100.00, 10);

        $cuenta = $this->abrirCuentaCon([['articulo' => $articulo, 'cantidad' => 10]]);
        $lineaId = $cuenta['lineas'][0]['id'];

        $this->postJson("/pos/cuentas/{$cuenta['id']}/cobrar", [
            'version' => $cuenta['version'],
            'lineas' => [['cuenta_linea_id' => $lineaId, 'cantidad' => 1]],
        ])->assertCreated();
    }

    public function test_un_cobro_parcial_que_por_si_solo_supera_el_tope_se_rechaza(): void
    {
        $this->montarSala(['cobro_dividido_activo' => true]);
        $articulo = $this->articuloPos(100.00, 10);

        $cuenta = $this->abrirCuentaCon([['articulo' => $articulo, 'cantidad' => 10]]);
        $lineaId = $cuenta['lineas'][0]['id'];

        // 5 unidades × 100 € + 10% = 550 €: por encima del tope de 400 €, aunque sea "solo" una
        // parte de la cuenta total.
        $this->postJson("/pos/cuentas/{$cuenta['id']}/cobrar", [
            'version' => $cuenta['version'],
            'lineas' => [['cuenta_linea_id' => $lineaId, 'cantidad' => 5]],
        ])->assertStatus(422);

        $this->assertDatabaseCount('facturas', 0);
    }
}
