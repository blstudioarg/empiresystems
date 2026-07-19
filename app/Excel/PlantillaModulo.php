<?php

namespace App\Excel;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

/**
 * Plantilla descargable de un módulo importable: cabeceras de `columnas()` + una fila con los
 * valores `ejemplo` de cada columna (FR-023). Las cabeceras proceden de la misma lista que usa
 * la exportación, así que coinciden por construcción (FR-024).
 */
class PlantillaModulo implements FromArray, WithHeadings
{
    public function __construct(private readonly DefinicionExcel $definicion) {}

    public function array(): array
    {
        return [
            array_map(fn (ColumnaExcel $columna) => $columna->ejemplo, $this->definicion->columnas()),
        ];
    }

    public function headings(): array
    {
        return array_map(fn (ColumnaExcel $columna) => $columna->etiqueta, $this->definicion->columnas());
    }
}
