<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Se lanza cuando el total (impuestos incluidos) de un ticket simplificado supera el tope legal
 * aplicable al tenant (400 € / 3.000 €). En ese caso debe emitirse una factura ordinaria.
 */
class TicketFueraDeTopeException extends RuntimeException
{
    public static function paraTope(float $tope): self
    {
        $topeFormateado = number_format($tope, 2, ',', '.');

        return new self(
            "El importe supera el máximo de una factura simplificada ({$topeFormateado} € IVA incl.). Emita una factura ordinaria."
        );
    }
}
