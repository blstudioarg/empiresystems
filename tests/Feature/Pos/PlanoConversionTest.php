<?php

namespace Tests\Feature\Pos;

use App\Support\PosPlanoCeldas;
use App\Support\PosPlanoConversion;
use PHPUnit\Framework\TestCase;

/**
 * Feature 040, D8 de research.md — conversión de los planos existentes a ocupación en celdas.
 *
 * El mapeo por forma (`rectangular`→2×1, `barra`→3×1) reproduce el dibujo actual, pero el dibujo
 * actual **ya se solapa** (es el defecto que la feature corrige), así que aplicarlo a ciegas dejaría
 * planos que la propia validación rechazaría. Estos tests fijan las tres garantías del paso de
 * corrección: nadie desaparece (SC-003), nadie viola G2/G3 (SC-002) y el resultado es determinista.
 *
 * No toca base de datos: la conversión es una función pura, y así también se puede razonar sobre
 * ella sin arrancar el framework.
 */
class PlanoConversionTest extends TestCase
{
    /** @param array<int, array{id:int,fila:int,columna:int,forma:string}> $mesas */
    private function assertSinViolaciones(array $mesas, array $resultado): void
    {
        $celdas = [];

        foreach ($mesas as $mesa) {
            $ocupacion = $resultado[$mesa['id']];
            $this->assertGreaterThanOrEqual(1, $ocupacion['ancho_celdas'], 'G1: ancho mínimo 1');
            $this->assertGreaterThanOrEqual(1, $ocupacion['alto_celdas'], 'G1: alto mínimo 1');

            $this->assertLessThanOrEqual(
                PosPlanoCeldas::COLUMNAS,
                $mesa['columna'] + $ocupacion['ancho_celdas'],
                "G2: la mesa {$mesa['id']} se sale de la rejilla"
            );
            $this->assertLessThanOrEqual(
                PosPlanoCeldas::FILAS,
                $mesa['fila'] + $ocupacion['alto_celdas'],
                "G2: la mesa {$mesa['id']} se sale de la rejilla"
            );

            for ($f = $mesa['fila']; $f < $mesa['fila'] + $ocupacion['alto_celdas']; $f++) {
                for ($c = $mesa['columna']; $c < $mesa['columna'] + $ocupacion['ancho_celdas']; $c++) {
                    $this->assertArrayNotHasKey("{$f}-{$c}", $celdas, "G3: solapamiento en ({$f}, {$c})");
                    $celdas["{$f}-{$c}"] = $mesa['id'];
                }
            }
        }
    }

    public function test_ninguna_mesa_desaparece_ni_viola_los_invariantes(): void
    {
        $mesas = [
            // Barra en la última columna: 3×1 se saldría de la rejilla (G2).
            ['id' => 1, 'fila' => 0, 'columna' => 7, 'forma' => 'barra'],
            // Rectangular pegada a otra mesa: 2×1 pisaría a la vecina (G3).
            ['id' => 2, 'fila' => 1, 'columna' => 0, 'forma' => 'rectangular'],
            ['id' => 3, 'fila' => 1, 'columna' => 1, 'forma' => 'cuadrada'],
            // Barra con dos celdas libres a la derecha: cabe entera.
            ['id' => 4, 'fila' => 3, 'columna' => 0, 'forma' => 'barra'],
            ['id' => 5, 'fila' => 4, 'columna' => 4, 'forma' => 'redonda'],
        ];

        $resultado = PosPlanoConversion::convertirZona($mesas);

        $this->assertCount(count($mesas), $resultado, 'SC-003: ninguna mesa desaparece');
        $this->assertSinViolaciones($mesas, $resultado);

        // La barra del borde se reduce hasta caber; la del centro conserva sus 3 celdas.
        $this->assertSame(1, $resultado[1]['ancho_celdas']);
        $this->assertSame(3, $resultado[4]['ancho_celdas']);
        // La rectangular cede ante la vecina que ya estaba asentada a su derecha.
        $this->assertSame(1, $resultado[2]['ancho_celdas']);
    }

    public function test_el_orden_de_reduccion_es_determinista(): void
    {
        $mesas = [
            ['id' => 9, 'fila' => 2, 'columna' => 3, 'forma' => 'cuadrada'],
            ['id' => 4, 'fila' => 2, 'columna' => 2, 'forma' => 'rectangular'],
            ['id' => 7, 'fila' => 0, 'columna' => 0, 'forma' => 'barra'],
        ];

        $esperado = PosPlanoConversion::convertirZona($mesas);

        // Mismo conjunto, orden de entrada distinto: el resultado no cambia (se ordena por
        // fila, columna, id antes de decidir quién cede). `assertEquals` y no `assertSame` porque
        // el mapa se indexa por id en el orden de entrada: lo que debe coincidir es la ocupación
        // de cada mesa, no en qué posición del array aparece.
        $this->assertEquals($esperado, PosPlanoConversion::convertirZona(array_reverse($mesas)));
        // Y la que cede es la de columna mayor, no la que llegó primero en el array.
        $this->assertSame(1, $esperado[4]['ancho_celdas']);
        $this->assertSame(3, $esperado[7]['ancho_celdas']);
    }

    public function test_una_mesa_sin_posicion_en_la_rejilla_queda_en_una_celda(): void
    {
        $mesas = [
            ['id' => 1, 'fila' => null, 'columna' => null, 'forma' => 'barra'],
        ];

        $this->assertSame(
            [1 => ['ancho_celdas' => 1, 'alto_celdas' => 1]],
            PosPlanoConversion::convertirZona($mesas)
        );
    }
}
