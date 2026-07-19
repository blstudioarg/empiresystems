<?php

namespace App\Excel;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

/**
 * Export genérico de Laravel Excel: una sola clase para los cinco módulos exportables,
 * parametrizada por su {@see DefinicionExportable} (research.md D7 — la alternativa de una clase
 * `Export` por módulo es justo el escenario en el que exportación/plantilla/importación se
 * desincronizan).
 *
 * `FromQuery` + `WithChunkReading` (en vez de `FromCollection`) para que 10.000 filas no agoten
 * memoria (SC-009, research.md D4).
 */
class ExportacionModulo implements FromQuery, WithChunkReading, WithColumnFormatting, WithHeadings, WithMapping
{
    /**
     * @param  int[]  $ids
     */
    public function __construct(
        private readonly DefinicionExportable $definicion,
        private readonly array $ids,
    ) {}

    public function query(): Builder
    {
        return $this->definicion->consultaExportacion($this->ids);
    }

    public function headings(): array
    {
        return array_map(fn (ColumnaExcel $columna) => $columna->etiqueta, $this->definicion->columnas());
    }

    /**
     * @param  Model  $registro
     */
    public function map($registro): array
    {
        return array_map(
            fn (ColumnaExcel $columna) => ($columna->exportar)($registro),
            $this->definicion->columnas(),
        );
    }

    public function chunkSize(): int
    {
        return 500;
    }

    public function columnFormats(): array
    {
        $formatos = [];

        foreach (array_values($this->definicion->columnas()) as $indice => $columna) {
            $codigo = match ($columna->formato) {
                FormatoCelda::Importe => '#,##0.00" €"',
                FormatoCelda::Numero => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1,
                FormatoCelda::Fecha => NumberFormat::FORMAT_DATE_DDMMYYYY,
                FormatoCelda::Texto, FormatoCelda::Booleano => NumberFormat::FORMAT_TEXT,
            };

            if ($codigo !== NumberFormat::FORMAT_TEXT) {
                $letra = Coordinate::stringFromColumnIndex($indice + 1);
                $formatos[$letra] = $codigo;
            }
        }

        return $formatos;
    }
}
