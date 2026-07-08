<?php

namespace App\Enums;

enum OrigenLead: string
{
    case Manual = 'manual';
    case Importacion = 'importacion';

    public function label(): string
    {
        return match ($this) {
            self::Manual => 'Alta manual',
            self::Importacion => 'Importación',
        };
    }
}
