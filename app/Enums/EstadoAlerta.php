<?php

namespace App\Enums;

enum EstadoAlerta: string
{
    case Nueva = 'nueva';
    case Vista = 'vista';
    case Resuelta = 'resuelta';

    public function label(): string
    {
        return match ($this) {
            self::Nueva => 'Nueva',
            self::Vista => 'Vista',
            self::Resuelta => 'Resuelta',
        };
    }
}
