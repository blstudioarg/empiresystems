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
}
