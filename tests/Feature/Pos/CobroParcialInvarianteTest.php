<?php

namespace Tests\Feature\Pos;

use App\Models\PosCobroLinea;
use App\Models\PosCuentaLinea;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MontaSalaPos;
use Tests\TestCase;

/**
 * FR-029/FR-033: para cada línea, la suma de `pos_cobro_lineas.cantidad` debe igualar
 * `cuenta_lineas.cantidad_saldada`. Es la comprobación que hace imposible cobrar dos veces la
 * misma unidad.
 */
class CobroParcialInvarianteTest extends TestCase
{
    use MontaSalaPos, RefreshDatabase;

    public function test_la_suma_de_cobro_lineas_iguala_la_cantidad_saldada(): void
    {
        $this->montarSala(['cobro_dividido_activo' => true]);
        $articulo = $this->articuloPos(10.00, 10);

        $cuenta = $this->abrirCuentaCon([['articulo' => $articulo, 'cantidad' => 4]]);
        $lineaId = $cuenta['lineas'][0]['id'];

        $this->postJson("/pos/cuentas/{$cuenta['id']}/cobrar", [
            'version' => $cuenta['version'],
            'lineas' => [['cuenta_linea_id' => $lineaId, 'cantidad' => 1]],
        ])->assertCreated();

        $version = $this->getJson("/pos/cuentas/{$cuenta['id']}")->json('version');

        $this->postJson("/pos/cuentas/{$cuenta['id']}/cobrar", [
            'version' => $version,
            'lineas' => [['cuenta_linea_id' => $lineaId, 'cantidad' => 2]],
        ])->assertCreated();

        $linea = PosCuentaLinea::find($lineaId);

        $this->assertSame(3.0, (float) $linea->cantidad_saldada);
        $this->assertSame(
            3.0,
            (float) PosCobroLinea::where('cuenta_linea_id', $lineaId)->sum('cantidad'),
        );
    }

    public function test_no_se_pueden_cobrar_mas_unidades_de_las_pendientes(): void
    {
        $this->montarSala(['cobro_dividido_activo' => true]);
        $articulo = $this->articuloPos(10.00, 10);

        $cuenta = $this->abrirCuentaCon([['articulo' => $articulo, 'cantidad' => 2]]);
        $lineaId = $cuenta['lineas'][0]['id'];

        $this->postJson("/pos/cuentas/{$cuenta['id']}/cobrar", [
            'version' => $cuenta['version'],
            'lineas' => [['cuenta_linea_id' => $lineaId, 'cantidad' => 5]],
        ])->assertStatus(422);

        $this->assertDatabaseCount('facturas', 0);
    }

    public function test_pedir_la_misma_unidad_dos_veces_en_llamadas_separadas_no_supera_lo_pendiente(): void
    {
        $this->montarSala(['cobro_dividido_activo' => true]);
        $articulo = $this->articuloPos(10.00, 10);

        $cuenta = $this->abrirCuentaCon([['articulo' => $articulo, 'cantidad' => 2]]);
        $lineaId = $cuenta['lineas'][0]['id'];

        $this->postJson("/pos/cuentas/{$cuenta['id']}/cobrar", [
            'version' => $cuenta['version'],
            'lineas' => [['cuenta_linea_id' => $lineaId, 'cantidad' => 2]],
        ])->assertCreated();

        $version = $this->getJson("/pos/cuentas/{$cuenta['id']}")->json('version');

        // Ya no queda nada pendiente: pedir 1 más debe rechazarse.
        $this->postJson("/pos/cuentas/{$cuenta['id']}/cobrar", [
            'version' => $version,
            'lineas' => [['cuenta_linea_id' => $lineaId, 'cantidad' => 1]],
        ])->assertStatus(422);
    }
}
