<?php

namespace App\Excel\Definiciones;

use App\Enums\EntidadLogActividad;
use App\Excel\ColumnaExcel;
use App\Excel\Concerns\OrdenaPorIds;
use App\Excel\DefinicionExportable;
use App\Excel\FormatoCelda;
use App\Models\Albaran;
use Illuminate\Database\Eloquent\Builder;
use PhpOffice\PhpSpreadsheet\Shared\Date as FechaExcel;

/**
 * Albaranes: solo exportable (data-model.md §3.5). No implementa `DefinicionImportable` — FR-010.
 */
class DefinicionAlbaranes implements DefinicionExportable
{
    use OrdenaPorIds;

    public function modulo(): string
    {
        return 'albaranes';
    }

    public function etiquetaModulo(): string
    {
        return 'albaranes';
    }

    public function permiso(): string
    {
        return 'ver-albaranes';
    }

    public function entidadLog(): EntidadLogActividad
    {
        return EntidadLogActividad::Albaran;
    }

    public function columnas(): array
    {
        return [
            new ColumnaExcel(
                clave: 'numero',
                etiqueta: 'Número',
                obligatoria: false,
                formato: FormatoCelda::Texto,
                exportar: fn (Albaran $a) => $a->numero,
            ),
            new ColumnaExcel(
                clave: 'receptor',
                etiqueta: 'Receptor',
                obligatoria: false,
                formato: FormatoCelda::Texto,
                exportar: fn (Albaran $a) => $a->receptor_nombre,
            ),
            new ColumnaExcel(
                clave: 'cliente',
                etiqueta: 'Cliente',
                obligatoria: false,
                formato: FormatoCelda::Texto,
                exportar: fn (Albaran $a) => $a->cliente?->razon_social ?: $a->cliente?->nombre,
            ),
            new ColumnaExcel(
                clave: 'estado',
                etiqueta: 'Estado',
                obligatoria: false,
                formato: FormatoCelda::Texto,
                exportar: fn (Albaran $a) => $a->estado->label(),
            ),
            new ColumnaExcel(
                clave: 'fecha_entrega',
                etiqueta: 'Fecha de entrega',
                obligatoria: false,
                formato: FormatoCelda::Fecha,
                exportar: fn (Albaran $a) => $a->fecha_entrega ? FechaExcel::PHPToExcel($a->fecha_entrega) : null,
            ),
            new ColumnaExcel(
                clave: 'total',
                etiqueta: 'Total',
                obligatoria: false,
                formato: FormatoCelda::Importe,
                exportar: fn (Albaran $a) => (float) $a->total,
            ),
        ];
    }

    public function consultaExportacion(array $ids): Builder
    {
        $query = Albaran::with('cliente')->whereIn('id', $ids);

        return $this->ordenarPorIds($query, $ids);
    }
}
