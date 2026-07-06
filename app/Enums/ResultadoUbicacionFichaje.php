<?php

namespace App\Enums;

enum ResultadoUbicacionFichaje: string
{
    case Dentro = 'dentro';
    case Fuera = 'fuera';
    case SinUbicacion = 'sin_ubicacion';

    public function label(): string
    {
        return match ($this) {
            self::Dentro => 'Dentro de ubicación',
            self::Fuera => 'Fuera de ubicación',
            self::SinUbicacion => 'Sin ubicación',
        };
    }
}
