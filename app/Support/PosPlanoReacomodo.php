<?php

namespace App\Support;

use App\Models\PosMesa;
use App\Models\PosZona;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Validación server-side del payload de guardado del plano (feature 039, data-model.md
 * "Validación de payload de guardado"; ampliada por la feature 040 a rectángulos de celdas).
 *
 * El servidor no confía en el cliente: aunque el bloqueo al redimensionar y el reacomodo por
 * colisión ocurren en cliente como previsualización, aquí se revalida todo como barrera real, y se
 * rechaza la petición **entera** (atómico) si algo no cumple.
 *
 * Comprueba los tres invariantes de geometría de la feature 040 (data-model.md):
 * G1 ocupación mínima, G2 dentro de la rejilla, G3 sin solapamiento. G3 es la novedad conceptual:
 * antes el invariante era "dos mesas no comparten celda", una comparación de puntos que las formas
 * alargadas burlaban por diseño.
 */
class PosPlanoReacomodo
{
    /**
     * @param  array<int, array{id: int|string, fila: int, columna: int, ancho_celdas: int, alto_celdas: int, forma: string}>  $mesasPayload
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

        // Clave "fila-columna" => nombre de la mesa que ya la ocupa. Marcar todas las celdas de cada
        // rectángulo (48 como máximo por zona) detecta a la vez el solapamiento y la salida de
        // rejilla, y conserva la forma del código anterior.
        $celdasVistas = [];

        foreach ($mesasPayload as $item) {
            $fila = (int) $item['fila'];
            $columna = (int) $item['columna'];
            $ancho = (int) $item['ancho_celdas'];
            $alto = (int) $item['alto_celdas'];
            $nombre = $mesasZona->get((int) $item['id'])->nombre;

            if ($ancho < 1 || $alto < 1) {
                throw ValidationException::withMessages([
                    'mesas' => "La mesa {$nombre} debe ocupar al menos una celda.",
                ]);
            }

            if ($fila < 0 || $columna < 0
                || $columna + $ancho > PosPlanoCeldas::COLUMNAS
                || $fila + $alto > PosPlanoCeldas::FILAS) {
                throw ValidationException::withMessages([
                    'mesas' => "La mesa {$nombre} se sale de la rejilla de la zona.",
                ]);
            }

            for ($f = $fila; $f < $fila + $alto; $f++) {
                for ($c = $columna; $c < $columna + $ancho; $c++) {
                    $clave = "{$f}-{$c}";
                    if (isset($celdasVistas[$clave])) {
                        throw ValidationException::withMessages([
                            'mesas' => "Las mesas {$celdasVistas[$clave]} y {$nombre} se solapan en el plano.",
                        ]);
                    }
                    $celdasVistas[$clave] = $nombre;
                }
            }
        }

        return $mesasZona;
    }
}
