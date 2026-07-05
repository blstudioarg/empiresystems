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

    public function label(): string
    {
        return match ($this) {
            self::Pendiente => 'Pendiente',
            self::Aprobado => 'Aprobado',
            self::Rechazado => 'Rechazado',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Pendiente => 'badge-warning',
            self::Aprobado => 'badge-success',
            self::Rechazado => 'badge-danger',
        };
    }
}
