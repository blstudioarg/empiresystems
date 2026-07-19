<?php

namespace App\Excel\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * Ordena una consulta `whereIn('id', $ids)` en el mismo orden en que llegaron los IDs — el orden
 * que el usuario ve en pantalla (contracts/exportacion.md). Compartido por las definiciones
 * exportables: es la misma línea en las cinco, no una abstracción prematura.
 *
 * `CASE WHEN` en vez de `FIELD()` (propio de MySQL/MariaDB): portable también a SQLite, que es el
 * driver de los tests (phpunit.xml) aunque el destino de producción sea MySQL/MariaDB.
 */
trait OrdenaPorIds
{
    /**
     * @param  int[]  $ids
     */
    private function ordenarPorIds(Builder $query, array $ids): Builder
    {
        $casos = [];

        foreach (array_values(array_map('intval', $ids)) as $posicion => $id) {
            $casos[] = "WHEN {$id} THEN {$posicion}";
        }

        return $query->orderByRaw('CASE id '.implode(' ', $casos).' END');
    }
}
