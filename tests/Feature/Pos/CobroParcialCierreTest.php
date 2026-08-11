<?php

namespace Tests\Feature\Pos;

use App\Models\PosCuenta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MontaSalaPos;
use Tests\TestCase;

/**
 * FR-028/FR-031: la mesa sigue ocupada mostrando el **pendiente** tras un cobro parcial; al
 * saldar la última unidad, la cuenta se cierra y la mesa se libera.
 */
class CobroParcialCierreTest extends TestCase
{
    use MontaSalaPos, RefreshDatabase;

    public function test_tras_un_cobro_parcial_la_mesa_sigue_ocupada_con_el_pendiente(): void
    {
        $this->montarSala(['cobro_dividido_activo' => true]);
        $articulo = $this->articuloPos(10.00, 10);

        $cuenta = $this->abrirCuentaCon([['articulo' => $articulo, 'cantidad' => 2]]);
        $lineaId = $cuenta['lineas'][0]['id'];

        $resultado = $this->postJson("/pos/cuentas/{$cuenta['id']}/cobrar", [
            'version' => $cuenta['version'],
            'lineas' => [['cuenta_linea_id' => $lineaId, 'cantidad' => 1]],
        ])->assertCreated()->json();

        $this->assertFalse($resultado['cuenta_cerrada']);
        $this->assertSame('11.00', $resultado['pendiente']);

        $sala = $this->getJson('/pos/sala')->json();
        $mesa = collect($sala['mesas'])->firstWhere('id', $this->mesasPos[1]->id);
        $this->assertSame('ocupada', $mesa['estado']);
        $this->assertSame('11.00', $mesa['pendiente']);
    }

    public function test_al_saldar_la_ultima_unidad_la_cuenta_se_cierra_y_la_mesa_se_libera(): void
    {
        $this->montarSala(['cobro_dividido_activo' => true]);
        $articulo = $this->articuloPos(10.00, 10);

        $cuenta = $this->abrirCuentaCon([['articulo' => $articulo, 'cantidad' => 2]]);
        $lineaId = $cuenta['lineas'][0]['id'];

        $this->postJson("/pos/cuentas/{$cuenta['id']}/cobrar", [
            'version' => $cuenta['version'],
            'lineas' => [['cuenta_linea_id' => $lineaId, 'cantidad' => 1]],
        ])->assertCreated();

        $version = $this->getJson("/pos/cuentas/{$cuenta['id']}")->json('version');

        $resultado = $this->postJson("/pos/cuentas/{$cuenta['id']}/cobrar", [
            'version' => $version,
            'lineas' => [['cuenta_linea_id' => $lineaId, 'cantidad' => 1]],
        ])->assertCreated()->json();

        $this->assertTrue($resultado['cuenta_cerrada']);
        $this->assertSame('0.00', $resultado['pendiente']);
        $this->assertSame(PosCuenta::ESTADO_CERRADA, PosCuenta::find($cuenta['id'])->estado);

        $sala = $this->getJson('/pos/sala')->json();
        $mesa = collect($sala['mesas'])->firstWhere('id', $this->mesasPos[1]->id);
        $this->assertSame('libre', $mesa['estado']);
    }
}
