<?php

namespace App\Exceptions;

use App\Models\CajaSesion;
use RuntimeException;

/** Ya hay una sesión de caja abierta en el tenant (FR-002). Lleva la sesión para mostrarla. */
class CajaYaAbiertaException extends RuntimeException
{
    public const CODIGO = 'caja_ya_abierta';

    public function __construct(public readonly ?CajaSesion $sesion)
    {
        parent::__construct('Ya hay una caja abierta.');
    }
}
