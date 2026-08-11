<?php

namespace Tests\Feature\Pos;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MontaSalaPos;
use Tests\TestCase;

/**
 * FR-029: pedir más unidades de las pendientes → 422; selección vacía en cobro parcial explícito
 * → 422; capacidad apagada → 422 (no se puede pedir cobro parcial si el tenant no lo activó).
 */
class CobroParcialLimitesTest extends TestCase
{
    use MontaSalaPos, RefreshDatabase;

    public function test_pedir_mas_unidades_de_las_pendientes_responde_422(): void
    {
        $this->montarSala(['cobro_dividido_activo' => true]);
        $articulo = $this->articuloPos(10.00, 10);

        $cuenta = $this->abrirCuentaCon([['articulo' => $articulo, 'cantidad' => 1]]);

        $this->postJson("/pos/cuentas/{$cuenta['id']}/cobrar", [
            'version' => $cuenta['version'],
            'lineas' => [['cuenta_linea_id' => $cuenta['lineas'][0]['id'], 'cantidad' => 3]],
        ])->assertStatus(422);
    }

    public function test_lineas_vacio_se_trata_como_cobro_total_no_como_error(): void
    {
        // Contrato (contracts/rutas.md): `lineas` omitido O vacío ⇒ cobro total. La selección
        // "vacía de verdad" que rechaza FR-029 es la que queda sin nada tras filtrar
        // cantidades ≤ 0 dentro de una selección con entradas (ver CobradorCuenta::resolverUnidades).
        $this->montarSala(['cobro_dividido_activo' => true]);
        $articulo = $this->articuloPos(10.00, 10);

        $cuenta = $this->abrirCuentaCon([['articulo' => $articulo, 'cantidad' => 1]]);

        $this->postJson("/pos/cuentas/{$cuenta['id']}/cobrar", [
            'version' => $cuenta['version'],
            'lineas' => [],
        ])->assertCreated();
    }

    public function test_con_la_capacidad_apagada_el_cobro_parcial_responde_422(): void
    {
        $this->montarSala(); // cobro_dividido_activo NO se activa
        $articulo = $this->articuloPos(10.00, 10);

        $cuenta = $this->abrirCuentaCon([['articulo' => $articulo, 'cantidad' => 2]]);

        $this->postJson("/pos/cuentas/{$cuenta['id']}/cobrar", [
            'version' => $cuenta['version'],
            'lineas' => [['cuenta_linea_id' => $cuenta['lineas'][0]['id'], 'cantidad' => 1]],
        ])->assertStatus(422);

        // Pero el cobro total (sin `lineas`) sigue funcionando igual.
        $this->postJson("/pos/cuentas/{$cuenta['id']}/cobrar", ['version' => $cuenta['version']])
            ->assertCreated();
    }
}
