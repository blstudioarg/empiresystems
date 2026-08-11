<?php

namespace Tests\Feature\Pos;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MontaSalaPos;
use Tests\TestCase;

/**
 * FR-025: el aviso de tope de la simplificada se calcula sobre el importe **pendiente** de la
 * cuenta, y llega **antes** de intentar cobrar.
 *
 * Que se mida sobre lo pendiente y no sobre lo consumido importa: con cobros parciales, una
 * cuenta grande puede haber bajado ya del tope y no tiene por qué bloquearse.
 */
class TopeCuentaTest extends TestCase
{
    use MontaSalaPos, RefreshDatabase;

    public function test_la_cuenta_expone_el_pendiente_para_que_la_interfaz_avise_antes_de_cobrar(): void
    {
        $this->montarSala();
        // Tope por defecto de la simplificada: 400 € (IVA incl.), sin sector ampliado.
        $articulo = $this->articuloPos(100.00, 10);

        $cuenta = $this->abrirCuentaCon([['articulo' => $articulo, 'cantidad' => 5]]);

        // 5 × 100 € + 10 % = 550 €, por encima del tope: la interfaz ya lo sabe sin cobrar.
        $this->assertSame('550.00', $cuenta['pendiente']);
        $this->assertDatabaseCount('facturas', 0);
    }

    public function test_cobrar_por_encima_del_tope_responde_422_y_no_emite(): void
    {
        $this->montarSala();
        $articulo = $this->articuloPos(100.00, 10);

        $cuenta = $this->abrirCuentaCon([['articulo' => $articulo, 'cantidad' => 5]]);

        $this->postJson("/pos/cuentas/{$cuenta['id']}/cobrar", ['version' => $cuenta['version']])
            ->assertStatus(422);

        $this->assertDatabaseCount('facturas', 0);

        // Y la cuenta sigue abierta con todo pendiente: nada quedó marcado como saldado.
        $this->assertSame('550.00', $this->getJson("/pos/cuentas/{$cuenta['id']}")->json('pendiente'));
    }

    public function test_el_tope_se_mide_sobre_lo_pendiente_no_sobre_lo_consumido(): void
    {
        $this->montarSala(['cobro_dividido_activo' => true]);
        $articulo = $this->articuloPos(100.00, 10);

        $cuenta = $this->abrirCuentaCon([['articulo' => $articulo, 'cantidad' => 5]]);
        $lineaId = $cuenta['lineas'][0]['id'];

        // Se cobran 2 unidades (220 €): por debajo del tope, se emite.
        $primera = $this->postJson("/pos/cuentas/{$cuenta['id']}/cobrar", [
            'version' => $cuenta['version'],
            'lineas' => [['cuenta_linea_id' => $lineaId, 'cantidad' => 2]],
        ])->assertCreated()->json();

        $this->assertFalse($primera['cuenta_cerrada']);
        $this->assertSame('330.00', $primera['pendiente']);

        // Lo que queda (330 €) también entra en el tope y se cobra sin problema, aunque el
        // consumo total de la cuenta (550 €) lo superara.
        $version = $this->getJson("/pos/cuentas/{$cuenta['id']}")->json('version');

        $segunda = $this->postJson("/pos/cuentas/{$cuenta['id']}/cobrar", ['version' => $version])
            ->assertCreated()
            ->json();

        $this->assertTrue($segunda['cuenta_cerrada']);
    }
}
