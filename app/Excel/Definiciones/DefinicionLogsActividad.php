<?php

namespace App\Excel\Definiciones;

use App\Enums\EntidadLogActividad;
use App\Excel\ColumnaExcel;
use App\Excel\Concerns\OrdenaPorIds;
use App\Excel\DefinicionExportable;
use App\Excel\FormatoCelda;
use App\Models\LogActividad;
use App\Support\AgenteUsuario;
use App\Support\GeolocalizadorIp;
use Illuminate\Database\Eloquent\Builder;

/**
 * Logs de actividad: solo exportable, sin importación (es un histórico append-only, no tiene
 * sentido cargarlo desde un fichero). La fecha se exporta como texto ya formateado en la zona
 * horaria del tenant (`enZonaTenant()`), igual que la muestra la pantalla — no como celda de fecha
 * de Excel, porque `FormatoCelda::Fecha` solo tiene formato día/mes/año (data-model.md §1 de
 * feature 031) y aquí la hora es el dato relevante de un evento de auditoría.
 */
class DefinicionLogsActividad implements DefinicionExportable
{
    use OrdenaPorIds;

    public function modulo(): string
    {
        return 'logs';
    }

    public function etiquetaModulo(): string
    {
        return 'logs de actividad';
    }

    public function permiso(): string
    {
        return 'ver-logs';
    }

    public function entidadLog(): EntidadLogActividad
    {
        return EntidadLogActividad::LogActividad;
    }

    public function columnas(): array
    {
        return [
            new ColumnaExcel(
                clave: 'fecha',
                etiqueta: 'Fecha',
                obligatoria: false,
                formato: FormatoCelda::Texto,
                exportar: fn (LogActividad $l) => $l->ocurrido_at->enZonaTenant()->format('d/m/Y H:i'),
            ),
            new ColumnaExcel(
                clave: 'usuario',
                etiqueta: 'Usuario',
                obligatoria: false,
                formato: FormatoCelda::Texto,
                exportar: fn (LogActividad $l) => $l->usuario_nombre,
            ),
            new ColumnaExcel(
                clave: 'accion',
                etiqueta: 'Acción',
                obligatoria: false,
                formato: FormatoCelda::Texto,
                exportar: fn (LogActividad $l) => $l->accion->label(),
            ),
            new ColumnaExcel(
                clave: 'resultado',
                etiqueta: 'Resultado',
                obligatoria: false,
                formato: FormatoCelda::Texto,
                exportar: fn (LogActividad $l) => $l->resultado->label(),
            ),
            new ColumnaExcel(
                clave: 'entidad',
                etiqueta: 'Entidad',
                obligatoria: false,
                formato: FormatoCelda::Texto,
                exportar: fn (LogActividad $l) => $l->entidad_tipo?->label() ?? 'Sin especificar',
            ),
            new ColumnaExcel(
                clave: 'descripcion',
                etiqueta: 'Detalle',
                obligatoria: false,
                formato: FormatoCelda::Texto,
                exportar: fn (LogActividad $l) => $l->descripcion,
            ),
            new ColumnaExcel(
                clave: 'ip_origen',
                etiqueta: 'IP',
                obligatoria: false,
                formato: FormatoCelda::Texto,
                exportar: fn (LogActividad $l) => $l->ip_origen,
            ),
            new ColumnaExcel(
                clave: 'navegador',
                etiqueta: 'Navegador',
                obligatoria: false,
                formato: FormatoCelda::Texto,
                exportar: fn (LogActividad $l) => AgenteUsuario::label($l->user_agent),
            ),
            new ColumnaExcel(
                clave: 'ubicacion',
                etiqueta: 'Ubicación',
                obligatoria: false,
                formato: FormatoCelda::Texto,
                exportar: fn (LogActividad $l) => GeolocalizadorIp::ubicacion($l->ip_origen),
            ),
        ];
    }

    public function consultaExportacion(array $ids): Builder
    {
        $query = LogActividad::whereIn('id', $ids);

        return $this->ordenarPorIds($query, $ids);
    }
}
