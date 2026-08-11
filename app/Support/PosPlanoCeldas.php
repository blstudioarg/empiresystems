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

    /** @return Collection<int, string> claves "fila-columna" ya ocupadas por mesas activas de la zona */
    private static function celdasOcupadas(int $zonaId): Collection
    {
        return PosMesa::query()
            ->where('zona_id', $zonaId)
            ->whereNotNull('fila')
            ->whereNotNull('columna')
            ->get(['fila', 'columna'])
            ->map(fn (PosMesa $mesa) => "{$mesa->fila}-{$mesa->columna}");
    }
}
