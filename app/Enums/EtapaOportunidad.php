<?php

namespace App\Enums;

enum EtapaOportunidad: string
{
    case Nueva = 'nueva';
    case EnNegociacion = 'en_negociacion';
    case Ganada = 'ganada';
    case Perdida = 'perdida';

    public function label(): string
    {
        return match ($this) {
            self::Nueva => 'Nueva',
            self::EnNegociacion => 'En negociación',
            self::Ganada => 'Ganada',
            self::Perdida => 'Perdida',
        };
    }

    public function esTerminal(): bool
    {
        return $this === self::Ganada || $this === self::Perdida;
    }
}
