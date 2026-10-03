<?php

namespace App\Exceptions;

use App\Models\CajaSesion;
use RuntimeException;

/**
 * La sesión que el usuario estaba contando ya la cerró otro dispositivo (FR-014). Lleva la sesión
 * cerrada para ofrecer su informe en vez de un error sin salida.
 */
class CajaYaCerradaException extends RuntimeException
{
    public const CODIGO = 'caja_ya_cerrada';

    public function __construct(public readonly CajaSesion $sesion)
    {
        $quien = $sesion->cerradaPor?->name;
        $hora = $sesion->cerrada_at?->enZonaTenant()->format('H:i');

        parent::__construct($quien && $hora
            ? "Esta caja ya la cerró {$quien} a las {$hora}."
            : 'Esta caja ya está cerrada.');
    }
}
