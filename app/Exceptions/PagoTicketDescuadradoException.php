<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Se lanza cuando el desglose de métodos de pago de un ticket (pago dividido) no suma exactamente
 * el total del ticket. El reparto se valida siempre en backend (Principio III): el importe cobrado
 * debe cuadrar al céntimo con el total emitido.
 */
class PagoTicketDescuadradoException extends RuntimeException
{
    public static function paraTotal(float $total, float $asignado): self
    {
        $totalFmt = number_format($total, 2, ',', '.');
        $asignadoFmt = number_format($asignado, 2, ',', '.');

        return new self(
            "El reparto de pagos ({$asignadoFmt} €) no coincide con el total del ticket ({$totalFmt} €)."
        );
    }
}
