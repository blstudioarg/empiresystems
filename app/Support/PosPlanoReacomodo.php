<?php

namespace App\Support;

use App\Models\PosMesa;
use App\Models\PosZona;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Validación server-side del payload de guardado del plano (feature 039, data-model.md
 * "Validación de payload de guardado"; ampliada por la feature 040 a rectángulos de celdas y por
 * la 042 al lienzo propio de cada zona).
 *
 * El servidor no confía en el cliente: aunque el bloqueo al redimensionar, el reacomodo por
 * colisión y el bloqueo del recorte ocurren en cliente como previsualización, aquí se revalida todo
 * como barrera real, y se rechaza la petición **entera** (atómico) si algo no cumple.
 *
 * Invariantes de geometría (data-model.md de la feature 042):
 * G1 ocupación mínima, G2 dentro de la rejilla, G3 sin solapamiento, **G4** ninguna mesa sobre una
 * celda recortada, **G5** medidas en rango y al menos una celda de sala.
 *
 * **Todo se comprueba contra la geometría PROPUESTA en el payload, no contra la persistida** (D8):
 * encoger la zona y mover en el mismo gesto la mesa que estorbaba es una operación legítima, y
 * validar contra lo guardado la rechazaría sin motivo.
 */
class PosPlanoReacomodo
{
    /**
     * @param  array{columnas: int, filas: int, celdas_inactivas: array<int, string>}  $geometria geometría **propuesta**
     * @param  array<int, array{id: int|string, fila: int, columna: int, ancho_celdas: int, alto_celdas: int, forma: string}>  $mesasPayload
     * @return Collection<int, PosMesa> mesas activas de la zona, indexadas por id, listas para actualizar
     *
     * @throws ValidationException
     */
    public static function validar(PosZona $zona, array $geometria, array $mesasPayload): Collection
    {
        $columnas = (int) $geometria['columnas'];
        $filas = (int) $geometria['filas'];
        $inactivas = self::normalizarCeldas($geometria['celdas_inactivas'] ?? [], $columnas, $filas);

        // G5 — el lienzo tiene que seguir siendo una sala.
        if ($columnas < PosPlanoCeldas::MIN || $columnas > PosPlanoCeldas::MAX
            || $filas < PosPlanoCeldas::MIN || $filas > PosPlanoCeldas::MAX
            || ($columnas * $filas) - count($inactivas) < 1) {
            throw ValidationException::withMessages([
                'columnas' => 'La zona debe conservar al menos una celda de sala.',
            ]);
        }

        $mesasZona = PosMesa::query()->where('zona_id', $zona->id)->get()->keyBy('id');

        $idsPayload = collect($mesasPayload)->pluck('id')->map(fn ($id) => (int) $id);

        $idsAjenos = $idsPayload->diff($mesasZona->keys());
        if ($idsAjenos->isNotEmpty()) {
            throw ValidationException::withMessages([
                'mesas' => 'Alguna mesa del plano no pertenece a esta zona.',
            ]);
        }

        $recortadas = array_flip($inactivas);

        // Clave "fila-columna" => nombre de la mesa que ya la ocupa. Marcar todas las celdas de cada
        // rectángulo detecta a la vez el solapamiento y la salida de rejilla, y conserva la forma
        // del código anterior.
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
                || $columna + $ancho > $columnas
                || $fila + $alto > $filas) {
                throw ValidationException::withMessages([
                    'mesas' => "La mesa {$nombre} se sale de la rejilla de la zona.",
                ]);
            }

            for ($f = $fila; $f < $fila + $alto; $f++) {
                for ($c = $columna; $c < $columna + $ancho; $c++) {
                    $clave = "{$f}-{$c}";

                    // G4 — el recorte y las mesas se validan juntos: un recorte que dejara una mesa
                    // encima nunca llega a persistirse (FR-008).
                    if (isset($recortadas[$clave])) {
                        throw ValidationException::withMessages([
                            'mesas' => "La mesa {$nombre} está sobre una parte del plano que no es sala.",
                        ]);
                    }

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

    /**
     * Máscara de recorte lista para persistir (data-model.md, "Normalización de `celdas_inactivas`"):
     *
     * 1. Se descartan las claves mal formadas (no `"entero-entero"`).
     * 2. Se descartan las celdas **fuera de la rejilla propuesta**. Es la regla del edge case de
     *    encoger: el recorte de una celda que deja de existir no se recuerda, así que si más tarde
     *    la zona se agranda esa celda vuelve como suelo y no como un recorte fantasma.
     * 3. Se deduplica y se ordena por (fila, columna), para que el JSON persistido sea estable y
     *    dos guardados equivalentes produzcan exactamente el mismo valor.
     *
     * @param  array<int, mixed>  $celdas
     * @return array<int, string>
     */
    public static function normalizarCeldas(array $celdas, int $columnas, int $filas): array
    {
        $validas = [];

        foreach ($celdas as $celda) {
            if (! is_string($celda) || preg_match('/^(\d+)-(\d+)$/', $celda, $m) !== 1) {
                continue;
            }

            $fila = (int) $m[1];
            $columna = (int) $m[2];

            if ($fila >= $filas || $columna >= $columnas) {
                continue;
            }

            $validas["{$fila}-{$columna}"] = [$fila, $columna];
        }

        uasort($validas, fn (array $a, array $b) => [$a[0], $a[1]] <=> [$b[0], $b[1]]);

        return array_keys($validas);
    }
}
