<?php

namespace App\Excel;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

/**
 * Detalle descargable de las filas rechazadas de una importación confirmada (FR-022).
 */
class RechazosExport implements FromArray, WithHeadings
{
    /**
     * @param  array<int, array{fila: int, motivo: string}>  $rechazadas
     */
    public function __construct(private readonly array $rechazadas) {}

    public function array(): array
    {
        return array_map(fn (array $f) => [$f['fila'], $f['motivo']], $this->rechazadas);
    }

    public function headings(): array
    {
        return ['Fila', 'Motivo'];
    }
}
