<?php

namespace Tests\Feature\Pos;

use App\Models\Factura;
use App\Models\PosZona;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MontaSalaPos;
use Tests\TestCase;

/**
 * Principio III/IV + research.md D4: un ticket con dos tipos impositivos distintos y zona con
 * suplemento; el desglose por tipo suma exactamente el total. Es el test que justifica la
 * decisión de aplicar el suplemento **por línea** (multiplicando el precio unitario) en vez de
 * repartir un importe único entre varios tipos.
 */
class SuplementoZonaTiposMixtosTest extends TestCase
{
    use MontaSalaPos, RefreshDatabase;

    public function test_el_desglose_por_tipo_suma_exactamente_el_total_con_suplemento_y_tipos_mixtos(): void
    {
        $this->montarSala(['suplemento_zona_activo' => true]);

        $zona = PosZona::query()->find($this->zonaPos->id);
        $zona->update(['suplemento_porcentaje' => 10]);

        $bebida = $this->articuloPos(2.00, 10, ['nombre' => 'Bebida']); // tipo 10%
        $comida = $this->articuloPos(15.00, 21, ['nombre' => 'Plato']); // tipo 21%

        $cuenta = $this->abrirCuentaCon([
            ['articulo' => $bebida, 'cantidad' => 2],
            ['articulo' => $comida, 'cantidad' => 1],
        ]);

        $this->postJson("/pos/cuentas/{$cuenta['id']}/cobrar", ['version' => $cuenta['version']])
            ->assertCreated();

        $factura = Factura::first();

        // Suma de los impuestos desglosados por tipo == total de la factura, al céntimo.
        $sumaCuotas = (float) $factura->impuestos->sum('cuota');
        $sumaBases = (float) $factura->impuestos->sum('base_imponible');
        $this->assertEqualsWithDelta((float) $factura->total, round($sumaBases + $sumaCuotas, 2), 0.01);

        // Verificación manual del importe: bebida 2×2€×1.10=4,40€ +10%=4,84€;
        // plato 1×15€×1.10=16,50€ +21%=19,965€→19,97€ (redondeo por línea). Total = 24,81 €.
        $this->assertEqualsWithDelta(24.81, (float) $factura->total, 0.01);
    }
}
