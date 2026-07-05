<?php

namespace App\Enums;

enum UserRole: string
{
    case SuperAdmin = 'super_admin';
    case Admin = 'admin';
    case Usuario = 'usuario';

    public static function default(): self
    {
        return self::Usuario;
    }

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super Admin',
            self::Admin => 'Administrador',
            self::Usuario => 'Usuario',
        };
    }
}
