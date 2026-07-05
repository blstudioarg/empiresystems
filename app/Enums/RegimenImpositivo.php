<?php

namespace App\Enums;

enum RegimenImpositivo: string
{
    case Iva = 'iva';
    case Igic = 'igic';
    case Ipsi = 'ipsi';

    public function label(): string
    {
        return match ($this) {
            self::Iva => 'IVA',
            self::Igic => 'IGIC',
            self::Ipsi => 'IPSI',
        };
    }

    public function tipoPorDefecto(): float
    {
        return match ($this) {
            self::Iva => 21,
            self::Igic => 7,
            self::Ipsi => 0,
        };
    }
}
