<?php

namespace App\Enums;

/**
 * Estado del ciclo comercial B2B del lado receptor de una factura electrónica recibida
 * (feature 022). Ortogonal al `estado` de stock/compra (borrador/confirmada/anulada).
 */
enum EstadoB2b: string
{
    case Recibida = 'recibida';
    case Aceptada = 'aceptada';
    case Rechazada = 'rechazada';
    case Pagada = 'pagada';

    public function label(): string
    {
        return match ($this) {
            self::Recibida => 'Recibida',
            self::Aceptada => 'Aceptada',
            self::Rechazada => 'Rechazada',
            self::Pagada => 'Pagada',
        };
    }
}
