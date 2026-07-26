<?php

namespace App\Excel\Definiciones;

use App\Enums\EntidadLogActividad;
use App\Excel\ColumnaExcel;
use App\Excel\Concerns\OrdenaPorIds;
use App\Excel\DefinicionExportable;
use App\Excel\FormatoCelda;
use App\Models\Lead;
use Illuminate\Database\Eloquent\Builder;
use PhpOffice\PhpSpreadsheet\Shared\Date as FechaExcel;

/**
 * Leads: solo exportable (data-model.md §3.6). La importación de leads ya existe
 * (`ImportadorLeads`, feature 028) y no se toca — esta definición añade únicamente su
 * exportación, por eso no implementa `DefinicionImportable`.
 */
class DefinicionLeads implements DefinicionExportable
{
    use OrdenaPorIds;

    public function modulo(): string
    {
        return 'leads';
    }

    public function etiquetaModulo(): string
    {
        return 'leads';
    }

    public function permiso(): string
    {
        return 'ver-leads';
    }

    public function entidadLog(): EntidadLogActividad
    {
        return EntidadLogActividad::Lead;
    }

    public function columnas(): array
    {
        return [
            new ColumnaExcel(
                clave: 'nombre',
                etiqueta: 'Nombre',
                obligatoria: false,
                formato: FormatoCelda::Texto,
                exportar: fn (Lead $l) => $l->nombre,
            ),
            new ColumnaExcel(
                clave: 'empresa',
                etiqueta: 'Empresa',
                obligatoria: false,
                formato: FormatoCelda::Texto,
                exportar: fn (Lead $l) => $l->empresa,
            ),
            new ColumnaExcel(
                clave: 'email',
                etiqueta: 'Email',
                obligatoria: false,
                formato: FormatoCelda::Texto,
                exportar: fn (Lead $l) => $l->email,
            ),
            new ColumnaExcel(
                clave: 'telefono',
                etiqueta: 'Teléfono',
                obligatoria: false,
                formato: FormatoCelda::Texto,
                exportar: fn (Lead $l) => $l->telefono,
            ),
            new ColumnaExcel(
                clave: 'estado',
                etiqueta: 'Estado',
                obligatoria: false,
                formato: FormatoCelda::Texto,
                exportar: fn (Lead $l) => $l->estado->label(),
            ),
            new ColumnaExcel(
                clave: 'origen',
                etiqueta: 'Origen',
                obligatoria: false,
                formato: FormatoCelda::Texto,
                exportar: fn (Lead $l) => $l->origen->label(),
            ),
            new ColumnaExcel(
                clave: 'canal_captacion',
                etiqueta: 'Canal de captación',
                obligatoria: false,
                formato: FormatoCelda::Texto,
                exportar: fn (Lead $l) => $l->canalCaptacion?->nombre ?? 'Sin especificar',
            ),
            new ColumnaExcel(
                clave: 'asignado_a',
                etiqueta: 'Asignado a',
                obligatoria: false,
                formato: FormatoCelda::Texto,
                exportar: fn (Lead $l) => $l->asignadoA?->name,
            ),
            new ColumnaExcel(
                clave: 'fecha_alta',
                etiqueta: 'Fecha de alta',
                obligatoria: false,
                formato: FormatoCelda::Fecha,
                exportar: fn (Lead $l) => $l->created_at ? FechaExcel::PHPToExcel($l->created_at) : null,
            ),
        ];
    }

    public function consultaExportacion(array $ids): Builder
    {
        $query = Lead::with(['asignadoA', 'canalCaptacion'])->whereIn('id', $ids);

        return $this->ordenarPorIds($query, $ids);
    }
}
