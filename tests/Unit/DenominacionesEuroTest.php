<?php

namespace Tests\Unit;

use App\Support\DenominacionesEuro;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * Catálogo de billetes y monedas de euro para el arqueo de caja (feature 048, research D7).
 * El total se suma en céntimos enteros: es el número que decide si la caja cuadra, así que un
 * residuo de coma flotante sería un descuadre inventado.
 */
class DenominacionesEuroTest extends TestCase
{
    public function test_expone_las_quince_denominaciones_de_mayor_a_menor(): void
    {
        $this->assertSame(
            [50000, 20000, 10000, 5000, 2000, 1000, 500, 200, 100, 50, 20, 10, 5, 2, 1],
            DenominacionesEuro::valores(),
        );
    }

    public function test_separa_billetes_de_monedas(): void
    {
        $billetes = array_column(DenominacionesEuro::billetes(), 'centimos');
        $monedas = array_column(DenominacionesEuro::monedas(), 'centimos');

        $this->assertSame([50000, 20000, 10000, 5000, 2000, 1000, 500], $billetes);
        $this->assertSame([200, 100, 50, 20, 10, 5, 2, 1], $monedas);
    }

    public function test_total_sin_residuos_de_coma_flotante(): void
    {
        // 3 × 50 € + 7 × 1 cént. = 150,07 €. Con floats, 0.01 * 7 ya no es 0.07 exacto.
        $this->assertSame('150.07', DenominacionesEuro::totalDesde(['5000' => 3, '1' => 7]));
        $this->assertSame('0.30', DenominacionesEuro::totalDesde(['10' => 3]));
        $this->assertSame('0.00', DenominacionesEuro::totalDesde([]));
    }

    public function test_total_de_un_cajon_realista(): void
    {
        $conteo = ['5000' => 3, '2000' => 7, '1000' => 4, '500' => 2, '200' => 5, '100' => 8, '50' => 6, '20' => 10, '5' => 3];

        // 150 + 140 + 40 + 10 + 10 + 8 + 3 + 2 + 0,15 = 363,15
        $this->assertSame('363.15', DenominacionesEuro::totalDesde($conteo));
    }

    public function test_normalizar_descarta_ceros_y_castea_a_entero(): void
    {
        $this->assertSame(
            ['5000' => 3, '1' => 2],
            DenominacionesEuro::normalizar(['5000' => '3', '2000' => 0, '1' => 2]),
        );
    }

    public function test_rechaza_una_denominacion_que_no_existe(): void
    {
        $this->expectException(InvalidArgumentException::class);

        DenominacionesEuro::totalDesde(['300' => 1]);
    }

    public function test_rechaza_cantidades_negativas(): void
    {
        $this->expectException(InvalidArgumentException::class);

        DenominacionesEuro::totalDesde(['5000' => -1]);
    }

    public function test_es_valida(): void
    {
        $this->assertTrue(DenominacionesEuro::esValida(5000));
        $this->assertFalse(DenominacionesEuro::esValida(300));
    }
}
