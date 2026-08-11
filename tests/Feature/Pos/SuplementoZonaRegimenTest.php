<?php

namespace Tests\Feature\Pos;

use App\Models\Factura;
use App\Models\PosZona;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\MontaSalaPos;
use Tests\TestCase;

/**
 * Principio II: el suplemento de zona se calcula correctamente bajo IVA, IGIC e IPSI. **Nada
 * asume IVA**: el suplemento solo multiplica el precio unitario, así que hereda automáticamente
 * el impuesto que corresponda por régimen — pero se demuestra, no se afirma.
 */
class SuplementoZonaRegimenTest extends TestCase
{
    use MontaSalaPos, RefreshDatabase;

    /** @return array<string, array{string}> */
    public static function regimenes(): array
    {
        return ['iva' => ['iva'], 'igic' => ['igic'], 'ipsi' => ['ipsi']];
    }

    #[DataProvider('regimenes')]
    public function test_el_suplemento_se_calcula_correctamente_bajo_cada_regimen(string $regimen): void
    {
        $this->montarSala(['suplemento_zona_activo' => true], atributosTenant: ['regimen_impositivo' => $regimen]);

        PosZona::query()->find($this->zonaPos->id)->update(['suplemento_porcentaje' => 20]);

        $articulo = $this->articuloPos(10.00, 7); // tipo bajo, válido en los tres regímenes

        $cuenta = $this->abrirCuentaCon([['articulo' => $articulo, 'cantidad' => 1]]);

        $this->postJson("/pos/cuentas/{$cuenta['id']}/cobrar", ['version' => $cuenta['version']])
            ->assertCreated();

        $factura = Factura::first();

        $this->assertSame($regimen, $factura->regimen_impositivo->value);
        // 10 € × 1.20 (suplemento) = 12 € base, +7% = 12,84 €.
        $this->assertEqualsWithDelta(12.84, (float) $factura->total, 0.01);
    }
}
