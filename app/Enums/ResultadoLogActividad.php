<?php

namespace App\Enums;

enum ResultadoLogActividad: string
{
    case Exito = 'exito';
    case Fallo = 'fallo';

    public function label(): string
    {
        return match ($this) {
            self::Exito => 'Éxito',
            self::Fallo => 'Fallo',
        };
    }
}
