<?php

namespace App\Support;

use App\Models\PosMesa;
use Illuminate\Support\Collection;

/**
 * Rejilla del plano de sala (feature 039, D2/D7 de research.md): 8 columnas × 6 filas por zona.
 * Reutilizado por el backfill de la migración y por la creación de mesas nuevas (FR-014/FR-015).
 */
class PosPlanoCeldas
{
    public const COLUMNAS = 8;

    public const FILAS = 6;

    /**
     * Primera celda libre de la zona, recorriendo fila por fila, columna por columna. `null` si la
     * rejilla ya está completa (48 mesas).
     *
     * @return array{fila: int, columna: int}|null
     */
    public static function primeraCeldaLibre(int $zonaId): ?array
    {
        $ocupadas = self::celdasOcupadas($zonaId);

        for ($fila = 0; $fila < self::FILAS; $fila++) {
            for ($columna = 0; $columna < self::COLUMNAS; $columna++) {
                if (! $ocupadas->contains("{$fila}-{$columna}")) {
                    return ['fila' => $fila, 'columna' => $columna];
                }
            }
        }

        return null;
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
