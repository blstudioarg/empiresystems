<?php

namespace App\Support;

class Formato
{
    /**
     * Cantidades (stock, unidades de línea): hasta 4 decimales, recortando ceros sobrantes.
     * Los campos `decimal:4` de Eloquent devuelven siempre un string de punto fijo
     * ("100.0000"); mostrarlo tal cual en una vista es el bug recurrente a evitar.
     */
    public static function cantidad(string|float|int|null $valor): string
    {
        return rtrim(rtrim(number_format((float) $valor, 4, ',', '.'), '0'), ',');
    }

    /**
     * Importes monetarios: siempre 2 decimales (nunca se recortan, a diferencia de cantidad()),
     * formato es-ES. Sin símbolo de moneda — añadir "€" en la vista si corresponde.
     */
    public static function moneda(string|float|int|null $valor): string
    {
        return number_format((float) $valor, 2, ',', '.');
    }

    /**
     * Porcentajes (tipo impositivo, recargo): 2 decimales, recortando ceros sobrantes.
     */
    public static function porcentaje(string|float|int|null $valor): string
    {
        return rtrim(rtrim(number_format((float) $valor, 2, ',', '.'), '0'), ',');
    }
}
