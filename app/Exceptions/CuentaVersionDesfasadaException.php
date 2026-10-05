<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * La versión que trae el TPV no es la de la cuenta en base de datos: otro dispositivo la modificó
 * (bloqueo optimista, feature 038 FR-024). La lanza la emisión de precuentas (feature 049) al
 * revalidar bajo bloqueo; el controlador la traduce al mismo 409 que el guardado.
 */
class CuentaVersionDesfasadaException extends RuntimeException
{
    public static function paraCuenta(int $cuentaId): self
    {
        return new self("La cuenta {$cuentaId} cambió en otro dispositivo.");
    }
}
