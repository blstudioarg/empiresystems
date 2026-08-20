<?php

namespace App\Support;

/**
 * Conversión de los planos de la feature 039 a ocupación en celdas (feature 040, D8 de research.md).
 *
 * Vive aquí y no dentro de la migración porque es la única parte de la migración con lógica de
 * verdad —qué mesa cede espacio y en qué orden— y así se puede probar como función pura
 * ({@see \Tests\Feature\Pos\PlanoConversionTest}) en vez de a través de un `artisan migrate`.
 *
 * El paso de corrección es imprescindible: el mapeo por forma reproduce el dibujo actual, y el
 * dibujo actual **ya se solapa** (es justo el defecto que la feature corrige), así que aplicarlo a
 * ciegas dejaría planos que la validación de {@see PosPlanoReacomodo} rechazaría al guardar.
 */
class PosPlanoConversion
{
    /**
     * Ocupación que la mesa ya *aparentaba* tener en pantalla, derivada de su forma. `tamano` no
     * interviene: era una escala de píxeles dentro de una misma celda, no una ocupación, así que
     * traducirla inventaría espacio que la mesa nunca reservó.
     *
     * @return array{0: int, 1: int} [ancho, alto] en celdas
     */
    public static function ocupacionDerivada(?string $forma): array
    {
        return match ($forma) {
            'rectangular' => [2, 1],
            'barra' => [3, 1],
            default => [1, 1],
        };
    }

    /**
     * Ocupación final de cada mesa de UNA zona, ya corregida para cumplir G1/G2/G3.
     *
     * Las celdas de origen de todas las mesas se reservan **antes** de expandir a nadie: antes de
     * esta feature dos mesas nunca compartían origen, así que reservarlas garantiza que 1×1 siempre
     * es una salida válida y que ninguna mesa desaparece del plano (SC-003). Después, en orden
     * determinista (`fila`, `columna`, `id`), cada mesa crece hasta donde quepa; la primera del
     * orden conserva su ocupación y las siguientes ceden.
     *
     * @param  array<int, array{id: int|string, fila: int|null, columna: int|null, forma: string|null}>  $mesas
     * @return array<int|string, array{ancho_celdas: int, alto_celdas: int}> indexado por id
     */
    public static function convertirZona(array $mesas): array
    {
        $enRejilla = array_values(array_filter(
            $mesas,
            fn (array $m) => $m['fila'] !== null && $m['columna'] !== null
        ));

        usort($enRejilla, fn (array $a, array $b) => [$a['fila'], $a['columna'], $a['id']] <=> [$b['fila'], $b['columna'], $b['id']]);

        $ocupadas = [];
        foreach ($enRejilla as $mesa) {
            $ocupadas["{$mesa['fila']}-{$mesa['columna']}"] = true;
        }

        $resultado = [];

        // Las mesas sin posición no se dibujan en el plano; quedan en la ocupación mínima.
        foreach ($mesas as $mesa) {
            $resultado[$mesa['id']] = ['ancho_celdas' => 1, 'alto_celdas' => 1];
        }

        foreach ($enRejilla as $mesa) {
            [$ancho, $alto] = self::ocupacionDerivada($mesa['forma']);

            while ($ancho > 1 && ! self::cabe($mesa, $ancho, $alto, $ocupadas)) {
                $ancho--;
            }

            if (! self::cabe($mesa, $ancho, $alto, $ocupadas)) {
                $ancho = 1;
                $alto = 1;
            }

            for ($f = $mesa['fila']; $f < $mesa['fila'] + $alto; $f++) {
                for ($c = $mesa['columna']; $c < $mesa['columna'] + $ancho; $c++) {
                    $ocupadas["{$f}-{$c}"] = true;
                }
            }

            $resultado[$mesa['id']] = ['ancho_celdas' => $ancho, 'alto_celdas' => $alto];
        }

        return $resultado;
    }

    /**
     * ¿Cabe el rectángulo dentro de la rejilla sin pisar una celda ya tomada? La celda de origen de
     * la propia mesa está reservada por ella misma y se ignora.
     *
     * @param  array{id: int|string, fila: int, columna: int, forma: string|null}  $mesa
     * @param  array<string, bool>  $ocupadas
     */
    private static function cabe(array $mesa, int $ancho, int $alto, array $ocupadas): bool
    {
        if ($mesa['columna'] + $ancho > PosPlanoCeldas::COLUMNAS || $mesa['fila'] + $alto > PosPlanoCeldas::FILAS) {
            return false;
        }

        for ($f = $mesa['fila']; $f < $mesa['fila'] + $alto; $f++) {
            for ($c = $mesa['columna']; $c < $mesa['columna'] + $ancho; $c++) {
                if ($f === $mesa['fila'] && $c === $mesa['columna']) {
                    continue;
                }
                if (isset($ocupadas["{$f}-{$c}"])) {
                    return false;
                }
            }
        }

        return true;
    }
}
