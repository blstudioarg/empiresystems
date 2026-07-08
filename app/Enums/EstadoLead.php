<?php

namespace App\Enums;

enum EstadoLead: string
{
    case Nuevo = 'nuevo';
    case Contactado = 'contactado';
    case Cualificado = 'cualificado';
    case Descartado = 'descartado';
    case Convertido = 'convertido';

    public function label(): string
    {
        return match ($this) {
            self::Nuevo => 'Nuevo',
            self::Contactado => 'Contactado',
            self::Cualificado => 'Cualificado',
            self::Descartado => 'Descartado',
            self::Convertido => 'Convertido',
        };
    }
}
