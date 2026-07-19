<?php

namespace App\Excel\Definiciones;

use App\Enums\EntidadLogActividad;
use App\Enums\EstadoFactura;
use App\Enums\TipoFactura;
use App\Excel\ColumnaExcel;
use App\Excel\Concerns\OrdenaPorIds;
use App\Excel\DefinicionExportable;
use App\Excel\FormatoCelda;
use App\Models\Factura;
use Illuminate\Database\Eloquent\Builder;
use PhpOffice\PhpSpreadsheet\Shared\Date as FechaExcel;

/**
 * Facturas: solo exportable (data-model.md §3.4). No implementa `DefinicionImportable` — FR-010,
 * Principio III: nada que toque numeración correlativa o Verifactu se puede importar. Excluye las
 * simplificadas, igual que hace hoy el listado (viven en el módulo POS).
 */
class DefinicionFacturas implements DefinicionExportable
{
    use OrdenaPorIds;

    public function modulo(): string
    {
        return 'facturas';
    }

    public function etiquetaModulo(): string
    {
        return 'facturas';
    }

    public function permiso(): string
    {
        return 'ver-facturas';
    }

    public function entidadLog(): EntidadLogActividad
    {
        return EntidadLogActividad::Factura;
    }

    public function columnas(): array
    {
        return [
            new ColumnaExcel(
                clave: 'numero',
                etiqueta: 'Número',
                obligatoria: false,
                formato: FormatoCelda::Texto,
                exportar: fn (Factura $f) => $f->numero_completo ?? 'Borrador',
            ),
            new ColumnaExcel(
                clave: 'estado',
                etiqueta: 'Estado',
                obligatoria: false,
                formato: FormatoCelda::Texto,
                exportar: fn (Factura $f) => self::estadoLabel($f->estado),
            ),
            new ColumnaExcel(
                clave: 'cliente',
                etiqueta: 'Cliente',
                obligatoria: false,
                formato: FormatoCelda::Texto,
                exportar: fn (Factura $f) => $f->cliente_razon_social ?: $f->cliente_nombre,
            ),
            new ColumnaExcel(
                clave: 'nif_cliente',
                etiqueta: 'NIF cliente',
                obligatoria: false,
                formato: FormatoCelda::Texto,
                exportar: fn (Factura $f) => $f->cliente_nif,
            ),
            new ColumnaExcel(
                clave: 'fecha_expedicion',
                etiqueta: 'Fecha de expedición',
                obligatoria: false,
                formato: FormatoCelda::Fecha,
                exportar: fn (Factura $f) => $f->fecha_expedicion ? FechaExcel::PHPToExcel($f->fecha_expedicion) : null,
            ),
            new ColumnaExcel(
                clave: 'fecha_vencimiento',
                etiqueta: 'Fecha de vencimiento',
                obligatoria: false,
                formato: FormatoCelda::Fecha,
                exportar: fn (Factura $f) => $f->fecha_vencimiento ? FechaExcel::PHPToExcel($f->fecha_vencimiento) : null,
            ),
            new ColumnaExcel(
                clave: 'base_imponible',
                etiqueta: 'Base imponible',
                obligatoria: false,
                formato: FormatoCelda::Importe,
                exportar: fn (Factura $f) => (float) $f->base_total,
            ),
            new ColumnaExcel(
                clave: 'cuota_impositiva',
                etiqueta: 'Cuota impositiva',
                obligatoria: false,
                formato: FormatoCelda::Importe,
                exportar: fn (Factura $f) => (float) $f->cuota_impuesto_total,
            ),
            new ColumnaExcel(
                clave: 'total',
                etiqueta: 'Total',
                obligatoria: false,
                formato: FormatoCelda::Importe,
                exportar: fn (Factura $f) => (float) $f->total,
            ),
            new ColumnaExcel(
                clave: 'cobrado',
                etiqueta: 'Cobrado',
                obligatoria: false,
                formato: FormatoCelda::Importe,
                exportar: fn (Factura $f) => $f->montoCobrado(),
            ),
            new ColumnaExcel(
                clave: 'pendiente',
                etiqueta: 'Pendiente',
                obligatoria: false,
                formato: FormatoCelda::Importe,
                exportar: fn (Factura $f) => $f->saldoPendiente(),
            ),
            new ColumnaExcel(
                clave: 'serie',
                etiqueta: 'Serie',
                obligatoria: false,
                formato: FormatoCelda::Texto,
                exportar: fn (Factura $f) => $f->serie?->codigo,
            ),
            new ColumnaExcel(
                clave: 'rectificativa',
                etiqueta: 'Rectificativa',
                obligatoria: false,
                formato: FormatoCelda::Booleano,
                exportar: fn (Factura $f) => $f->es_rectificativa ? 'Sí' : 'No',
            ),
        ];
    }

    public function consultaExportacion(array $ids): Builder
    {
        $query = Factura::with(['cliente', 'serie', 'rectificativa', 'pagosVigentes'])
            ->whereIn('id', $ids)
            ->where('tipo', '!=', TipoFactura::Simplificada->value);

        return $this->ordenarPorIds($query, $ids);
    }

    private static function estadoLabel(EstadoFactura $estado): string
    {
        return match ($estado) {
            EstadoFactura::Borrador => 'Borrador',
            EstadoFactura::Emitida => 'Emitida',
            EstadoFactura::Pagada => 'Pagada',
            EstadoFactura::Vencida => 'Vencida',
            EstadoFactura::Anulada => 'Anulada',
            EstadoFactura::Rectificada => 'Rectificada',
        };
    }
}
