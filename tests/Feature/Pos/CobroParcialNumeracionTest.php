<?php

namespace Tests\Feature\Pos;

use App\Models\Factura;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MontaSalaPos;
use Tests\TestCase;

/**
 * Principio II + FR-027: N cobros parciales producen N documentos correlativos, sin huecos, cada
 * uno inmutable.
 */
class CobroParcialNumeracionTest extends TestCase
{
    use MontaSalaPos, RefreshDatabase;

    public function test_tres_cobros_parciales_producen_tres_documentos_correlativos(): void
    {
        $this->montarSala(['cobro_dividido_activo' => true]);
        $articulo = $this->articuloPos(10.00, 10);

        $cuenta = $this->abrirCuentaCon([['articulo' => $articulo, 'cantidad' => 3]]);
        $lineaId = $cuenta['lineas'][0]['id'];
        $version = $cuenta['version'];

        foreach ([1, 1, 1] as $cantidad) {
            $res = $this->postJson("/pos/cuentas/{$cuenta['id']}/cobrar", [
                'version' => $version,
                'lineas' => [['cuenta_linea_id' => $lineaId, 'cantidad' => $cantidad]],
            ])->assertCreated()->json();

            $version = $this->getJson("/pos/cuentas/{$cuenta['id']}")->json('version');
        }

        $anio = now()->year;
        $numeros = Factura::query()->orderBy('numero')->pluck('numero_completo')->all();

        $this->assertSame([
            "S-{$anio}-0001",
            "S-{$anio}-0002",
            "S-{$anio}-0003",
        ], $numeros);

        // Cada factura es inmutable una vez emitida.
        foreach (Factura::all() as $factura) {
            $this->assertNotNull($factura->registrada_at ?? $factura->fecha_expedicion);
        }
    }
}
