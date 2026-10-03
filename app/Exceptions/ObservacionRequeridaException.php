<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * El descuadre supera el umbral del tenant y no se escribió qué pasó (FR-012). No se cierra nada:
 * lleva el resultado provisional para que la pantalla lo revele y pida la explicación.
 *
 * @phpstan-type Resultado array{efectivo_esperado: string, efectivo_contado: string, descuadre: string, estado: string}
 */
class ObservacionRequeridaException extends RuntimeException
{
    public const CODIGO = 'observacion_requerida';

    /** @param  Resultado  $resultado */
    public function __construct(public readonly array $resultado)
    {
        $importe = number_format(abs((float) $resultado['descuadre']), 2, ',', '.');

        parent::__construct("Hay una diferencia de {$importe} €. Escribe qué pasó para poder cerrar.");
    }
}
