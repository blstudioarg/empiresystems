<?php

namespace App\Enums;

enum EstadoUsuario: string
{
    case Pendiente = 'pendiente';
    case Aprobado = 'aprobado';
    case Rechazado = 'rechazado';

    public static function default(): self
    {
        return self::Pendiente;
    }
}
