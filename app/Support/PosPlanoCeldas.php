<?php

namespace App\Support;

use App\Models\PosMesa;
use App\Models\PosZona;
use Illuminate\Support\Collection;

/**
 * Lienzo del plano de sala.
 *
 * Hasta la feature 041 esto era **la** rejilla: 8×6 celdas, iguales para toda zona de todo tenant.
 * Desde la feature 042 el lienzo es un dato de cada zona (`columnas`, `filas`, `celdas_inactivas`)
 * y lo que queda aquí son los **defectos** y los **límites**: por eso `COLUMNAS`/`FILAS` pasaron a
 * llamarse `COLUMNAS_DEFECTO`/`FILAS_DEFECTO` (D3). El nombre viejo afirmaba algo que dejó de ser
 * cierto, y un nombre que miente se vuelve a usar mal.
 */
class PosPlanoCeldas
{
    /** Lienzo de una zona nueva, y de toda zona anterior a la feature 042. */
    public const COLUMNAS_DEFECTO = 8;

    public const FILAS_DEFECTO = 6;

    /** Mínimo por eje: por debajo de 4 celdas el lienzo deja de ser una sala. */
    public const MIN = 4;

    /** Máximo por eje: acota el lienzo a 576 celdas, que es lo que el navegador dibuja con soltura. */
    public const MAX = 24;

    /**
     * Primera celda **de suelo** libre de la zona, recorriendo fila por fila, columna por columna.
     * `null` si ya no queda ninguna.
     *
     * Recorre las medidas de ESA zona y se salta sus celdas recortadas (FR-020): una mesa nueva no
     * puede nacer sobre un patio ni fuera de una sala de 5×4 solo porque la rejilla antigua llegaba
     * hasta la columna 7.
     *
     * @return array{fila: int, columna: int}|null
     */
    public static function primeraCeldaLibre(PosZona $zona): ?array
    {
        $ocupadas = self::celdasOcupadas($zona->id);
        $inactivas = array_flip($zona->celdasInactivas());

        $filas = self::filasDe($zona);
        $columnas = self::columnasDe($zona);

        for ($fila = 0; $fila < $filas; $fila++) {
            for ($columna = 0; $columna < $columnas; $columna++) {
                $clave = "{$fila}-{$columna}";

                if (isset($inactivas[$clave])) {
                    continue;
                }

                if (! $ocupadas->contains($clave)) {
                    return ['fila' => $fila, 'columna' => $columna];
                }
            }
        }

        return null;
    }

    /** Ancho del lienzo de la zona, cayendo al defecto si el dato todavía no está poblado. */
    public static function columnasDe(PosZona $zona): int
    {
        return (int) ($zona->columnas ?: self::COLUMNAS_DEFECTO);
    }

    /** Alto del lienzo de la zona, cayendo al defecto si el dato todavía no está poblado. */
    public static function filasDe(PosZona $zona): int
    {
        return (int) ($zona->filas ?: self::FILAS_DEFECTO);
    }

    /**
     * Claves "fila-columna" ya ocupadas por mesas activas de la zona. Desde la feature 040 una mesa
     * ocupa un rectángulo, así que se marcan **todas** sus celdas y no solo su origen: si no, una
     * mesa nueva se crearía encima de una mesa grande.
     *
     * @return Collection<int, string>
     */
    private static function celdasOcupadas(int $zonaId): Collection
    {
        $ocupadas = collect();

        PosMesa::query()
            ->where('zona_id', $zonaId)
            ->whereNotNull('fila')
            ->whereNotNull('columna')
            ->get(['fila', 'columna', 'ancho_celdas', 'alto_celdas'])
            ->each(function (PosMesa $mesa) use ($ocupadas) {
                $ancho = max(1, (int) $mesa->ancho_celdas);
                $alto = max(1, (int) $mesa->alto_celdas);

                for ($fila = $mesa->fila; $fila < $mesa->fila + $alto; $fila++) {
                    for ($columna = $mesa->columna; $columna < $mesa->columna + $ancho; $columna++) {
                        $ocupadas->push("{$fila}-{$columna}");
                    }
                }
            });

        return $ocupadas;
    }
}
