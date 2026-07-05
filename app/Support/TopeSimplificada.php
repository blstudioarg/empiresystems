<?php

namespace App\Support;

use App\Models\Configuracion;

/**
 * Resuelve el tope de importe (impuestos incluidos) de una factura simplificada para el tenant
 * activo. Por defecto 400 €; 3.000 € si el tenant está marcado como sector con tope ampliado
 * (venta al por menor, hostelería, transporte de personas, peluquerías, aparcamiento, etc.).
 *
 * Ver docs/02-facturacion-espana.md §3.1 y docs/03-modelo-datos.md (configuraciones).
 */
class TopeSimplificada
{
    public const CLAVE = 'factura.simplificada_tope_ampliado';

    public const TOPE_BASE = 400.00;

    public const TOPE_AMPLIADO = 3000.00;

    /**
     * Tope aplicable para el tenant activo (resuelto por el global scope de tenant).
     */
    public function topePara(): float
    {
        return $this->sectorAmpliado() ? self::TOPE_AMPLIADO : self::TOPE_BASE;
    }

    public function sectorAmpliado(): bool
    {
        $configuracion = Configuracion::where('clave', self::CLAVE)->first();

        if (! $configuracion) {
            return false;
        }

        return in_array(strtolower((string) $configuracion->valor), ['1', 'true', 'on', 'yes'], true);
    }
}
