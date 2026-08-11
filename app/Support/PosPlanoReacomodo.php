<?php

namespace App\Support;

use App\Models\PosMesa;
use App\Models\PosZona;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Validación server-side del payload de guardado del plano (feature 039, data-model.md
 * "Validación de payload de guardado"). El servidor no confía en el cliente: aunque el reacomodo
 * por colisión (D3) ocurre solo en cliente como previsualización, aquí se revalida todo como
 * defensa en profundidad, y se rechaza la petición entera (atómico) si algo no cumple.
 */
class PosPlanoReacomodo
{
    /**
     * @param  array<int, array{id: int|string, fila: int, columna: int, forma: string, tamano: string}>  $mesasPayload
     * @return Collection<int, PosMesa> mesas activas de la zona, indexadas por id, listas para actualizar
     *
     * @throws ValidationException
     */
    public static function validar(PosZona $zona, array $mesasPayload): Collection
    {
        $mesasZona = PosMesa::query()->where('zona_id', $zona->id)->get()->keyBy('id');

        $idsPayload = collect($mesasPayload)->pluck('id')->map(fn ($id) => (int) $id);

        $idsAjenos = $idsPayload->diff($mesasZona->keys());
        if ($idsAjenos->isNotEmpty()) {
            throw ValidationException::withMessages([
                'mesas' => 'Alguna mesa del plano no pertenece a esta zona.',
            ]);
        }

        $celdasVistas = [];

        foreach ($mesasPayload as $item) {
            $fila = (int) $item['fila'];
            $columna = (int) $item['columna'];

            if ($fila < 0 || $fila >= PosPlanoCeldas::FILAS || $columna < 0 || $columna >= PosPlanoCeldas::COLUMNAS) {
                throw ValidationException::withMessages([
                    'mesas' => "La celda ({$fila}, {$columna}) está fuera de la rejilla de la zona.",
                ]);
            }

            $clave = "{$fila}-{$columna}";
            if (isset($celdasVistas[$clave])) {
                throw ValidationException::withMessages([
                    'mesas' => "Dos mesas del plano apuntan a la misma celda ({$fila}, {$columna}).",
                ]);
            }
            $celdasVistas[$clave] = true;
        }

        return $mesasZona;
    }
}
