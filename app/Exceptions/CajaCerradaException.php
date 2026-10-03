<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * No hay ninguna caja abierta en el tenant. Cobrar exige caja abierta (feature 048, FR-020): la
 * lanza `RegistroTicket` y la traducen a 409 `caja_cerrada` el TPV y el cobro de cuentas, para que
 * la pantalla ofrezca abrir la caja sin perder el ticket armado.
 */
class CajaCerradaException extends RuntimeException
{
    public const CODIGO = 'caja_cerrada';

    public static function paraCobrar(): self
    {
        return new self('La caja está cerrada. Ábrela para poder cobrar.');
    }

    public static function sinSesion(): self
    {
        return new self('No hay ninguna caja abierta.');
    }
}
