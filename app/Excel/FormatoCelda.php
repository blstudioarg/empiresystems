<?php

namespace App\Excel;

/**
 * Formato de celda al exportar (research.md D5). Solo Numero/Importe/Fecha reciben un formato de
 * celda especial en el .xlsx — Texto y Booleano se exportan como texto plano (el propio callable
 * `exportar` de la columna ya devuelve el valor legible, p. ej. "Sí"/"No").
 */
enum FormatoCelda
{
    case Texto;
    case Numero;
    case Importe;
    case Fecha;
    case Booleano;
}
