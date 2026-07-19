<?php

namespace App\Excel\Concerns;

/**
 * Normalización de valores leídos de una fila de fichero, compartida por las definiciones
 * importables. Los valores llegan como lo que Maatwebsite/PhpSpreadsheet decida darnos (string,
 * int, float, bool o null según la celda), nunca garantizado string.
 */
trait NormalizaValores
{
    /**
     * Cadena recortada, o `null` si queda vacía — para columnas de texto opcionales.
     */
    private static function texto(mixed $valor): ?string
    {
        $valor = trim((string) ($valor ?? ''));

        return $valor === '' ? null : $valor;
    }

    /**
     * Interpreta textos habituales de "sí"/"no" en una hoja rellenada a mano, además de los
     * valores que ya vienen como bool nativo al exportar y reimportar (SC-007).
     */
    private static function booleano(mixed $valor): bool
    {
        if (is_bool($valor)) {
            return $valor;
        }

        $texto = mb_strtolower(trim((string) ($valor ?? '')));

        return in_array($texto, ['si', 'sí', 'true', '1', 'x', 'yes'], true);
    }
}
