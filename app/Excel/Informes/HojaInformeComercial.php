<?php

namespace App\Excel\Informes;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

/**
 * Hoja genérica de tabla (cabeceras + filas) para el exportador del informe comercial (research
 * D7, feature 033): no reutiliza el contrato `DefinicionExportable` de la feature 031 porque ese
 * describe "N filas de un modelo seleccionadas por IDs" y este informe es un agregado multi-bloque
 * sin modelo de respaldo. Sí reutiliza la infraestructura de Laravel Excel.
 */
class HojaInformeComercial implements FromArray, WithHeadings, WithTitle
{
    /**
     * @param  list<string>  $cabeceras
     * @param  list<list<mixed>>  $filas
     */
    public function __construct(
        private readonly string $titulo,
        private readonly array $cabeceras,
        private readonly array $filas,
    ) {}

    public function array(): array
    {
        return $this->filas;
    }

    public function headings(): array
    {
        return $this->cabeceras;
    }

    public function title(): string
    {
        return $this->titulo;
    }
}
