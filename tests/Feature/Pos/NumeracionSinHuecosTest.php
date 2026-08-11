<?php

namespace Tests\Feature\Pos;

use App\Models\Factura;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MontaSalaPos;
use Tests\TestCase;

/**
 * Principio II + SC-008: **abrir y anular cuentas no consume numeración.** Los tickets emitidos
 * después siguen correlativos y sin huecos.
 *
 * Es el riesgo clásico de un TPV con cuentas: si la cuenta reservara número al abrirse, cada mesa
 * anulada dejaría un hueco en la serie y la numeración dejaría de ser correlativa — que es una
 * infracción, no una molestia.
 */
class NumeracionSinHuecosTest extends TestCase
{
    use MontaSalaPos, RefreshDatabase;

    public function test_abrir_y_anular_cuentas_no_deja_huecos_en_la_serie(): void
    {
        $this->montarSala(mesas: 4);
        $articulo = $this->articuloPos(10.00, 10);

        // Tres cuentas abiertas y anuladas: ninguna debe tocar la numeración.
        foreach ([1, 2, 3] as $i) {
            $cuenta = $this->abrirCuentaCon([['articulo' => $articulo]], $this->mesasPos[$i]);
            $this->postJson("/pos/cuentas/{$cuenta['id']}/anular")->assertOk();
        }

        $this->assertDatabaseCount('facturas', 0);

        // Ahora tres cobros reales, uno tras otro.
        foreach ([1, 2, 3] as $i) {
            $cuenta = $this->abrirCuentaCon([['articulo' => $articulo]], $this->mesasPos[$i]);
            $this->postJson("/pos/cuentas/{$cuenta['id']}/cobrar", ['version' => $cuenta['version']])
                ->assertCreated();
        }

        $anio = now()->year;
        $numeros = Factura::query()->orderBy('numero')->pluck('numero_completo')->all();

        $this->assertSame([
            "S-{$anio}-0001",
            "S-{$anio}-0002",
            "S-{$anio}-0003",
        ], $numeros);
    }

    public function test_una_cuenta_anulada_tras_haber_cobrado_no_altera_la_correlatividad(): void
    {
        $this->montarSala(['cobro_dividido_activo' => true], mesas: 3);
        $articulo = $this->articuloPos(10.00, 10);

        $cuenta = $this->abrirCuentaCon([['articulo' => $articulo, 'cantidad' => 2]]);
        $lineaId = $cuenta['lineas'][0]['id'];

        // Se cobra 1 de 2 unidades…
        $this->postJson("/pos/cuentas/{$cuenta['id']}/cobrar", [
            'version' => $cuenta['version'],
            'lineas' => [['cuenta_linea_id' => $lineaId, 'cantidad' => 1]],
        ])->assertCreated();

        // …y luego se anula el resto.
        $this->postJson("/pos/cuentas/{$cuenta['id']}/anular")->assertOk();

        // Un ticket posterior sigue la serie sin saltos.
        $otra = $this->abrirCuentaCon([['articulo' => $articulo]], $this->mesasPos[2]);
        $this->postJson("/pos/cuentas/{$otra['id']}/cobrar", ['version' => $otra['version']])->assertCreated();

        $anio = now()->year;
        $this->assertSame(
            ["S-{$anio}-0001", "S-{$anio}-0002"],
            Factura::query()->orderBy('numero')->pluck('numero_completo')->all(),
        );
    }
}
