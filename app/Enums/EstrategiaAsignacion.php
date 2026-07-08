<?php

namespace App\Enums;

enum EstrategiaAsignacion: string
{
    case Manual = 'manual';
    case RoundRobin = 'round_robin';

    public function label(): string
    {
        return match ($this) {
            self::Manual => 'Manual',
            self::RoundRobin => 'Reparto equitativo (round-robin)',
        };
    }
}
