<?php

namespace App\Enums;

enum EstadoPresupuesto: string
{
    case Borrador = 'borrador';
    case Enviado = 'enviado';
    case Aceptado = 'aceptado';
    case Rechazado = 'rechazado';
    case Caducado = 'caducado';
    case Facturado = 'facturado';

    public function label(): string
    {
        return match ($this) {
            self::Borrador => 'Borrador',
            self::Enviado => 'Enviado',
            self::Aceptado => 'Aceptado',
            self::Rechazado => 'Rechazado',
            self::Caducado => 'Caducado',
            self::Facturado => 'Facturado',
        };
    }

    public function esEditable(): bool
    {
        return $this === self::Borrador;
    }
}
