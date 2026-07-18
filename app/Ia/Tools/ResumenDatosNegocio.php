<?php

namespace App\Ia\Tools;

use App\Enums\EstadoFactura;
use App\Models\Cliente;
use App\Models\Factura;
use App\Models\Presupuesto;

/**
 * Tool de lectura agregada: cifras generales del negocio del tenant (US2). Datos mínimos,
 * bajo TenantScope. Complementa al dashboard sin exponer dumps de tablas.
 */
class ResumenDatosNegocio extends ToolAsistente
{
    public function nombre(): string
    {
        return 'resumen_datos_negocio';
    }

    public function descripcion(): string
    {
        return 'Devuelve un resumen agregado del negocio: número de clientes, facturas por estado, total facturado (emitidas + pagadas) y presupuestos abiertos. Úsala para preguntas globales tipo "¿cuánto llevo facturado?" o "¿cuántos clientes tengo?".';
    }

    public function schema(): array
    {
        return [
            'type' => 'object',
            'properties' => (object) [],
            'required' => [],
        ];
    }

    public function permisoRequerido(): string
    {
        return 'ver-dashboard';
    }

    public function esLectura(): bool
    {
        return true;
    }

    public function ejecutar(array $parametros): array
    {
        $facturadas = [EstadoFactura::Emitida->value, EstadoFactura::Pagada->value];

        return [
            'clientes' => Cliente::query()->count(),
            'facturas' => [
                'borrador' => Factura::query()->where('estado', EstadoFactura::Borrador->value)->count(),
                'emitidas' => Factura::query()->where('estado', EstadoFactura::Emitida->value)->count(),
                'pagadas' => Factura::query()->where('estado', EstadoFactura::Pagada->value)->count(),
            ],
            'total_facturado' => (float) Factura::query()->whereIn('estado', $facturadas)->sum('total'),
            'presupuestos_abiertos' => Presupuesto::query()
                ->whereIn('estado', ['borrador', 'enviado'])
                ->count(),
        ];
    }
}
