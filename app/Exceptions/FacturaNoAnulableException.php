<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * La factura (o el ticket) no se puede anular. Lleva un código para que cada pantalla dé su propio
 * mensaje (facturas ordinarias y POS hablan distinto, y el POS además lo traduce).
 */
class FacturaNoAnulableException extends RuntimeException
{
    public const NO_EMITIDA = 'no_emitida';

    public const CON_COBROS = 'con_cobros';

    public const RECTIFICADA = 'rectificada';

    public function __construct(public readonly string $codigo)
    {
        parent::__construct($codigo);
    }
}
