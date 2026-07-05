<?php

namespace App\Enums;

enum AccionLogActividad: string
{
    case Login = 'login';
    case Logout = 'logout';
    case Alta = 'alta';
    case Baja = 'baja';
    case Modificacion = 'modificacion';

    public function label(): string
    {
        return match ($this) {
            self::Login => 'Inicio de sesión',
            self::Logout => 'Cierre de sesión',
            self::Alta => 'Alta',
            self::Baja => 'Baja',
            self::Modificacion => 'Modificación',
        };
    }
}
