<?php

namespace App\Enums;

enum EstadoAlbaran: string
{
    case Borrador = 'borrador';
    case Entregado = 'entregado';
    case Facturado = 'facturado';
    case Anulado = 'anulado';

    public function label(): string
    {
        return match ($this) {
            self::Borrador => 'Borrador',
            self::Entregado => 'Entregado',
            self::Facturado => 'Facturado',
            self::Anulado => 'Anulado',
        };
    }

    public function esTerminal(): bool
    {
        return $this === self::Facturado || $this === self::Anulado;
    }

    public function esEditable(): bool
    {
        return $this === self::Borrador;
    }
}
