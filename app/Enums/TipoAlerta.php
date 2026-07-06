<?php

namespace App\Enums;

enum TipoAlerta: string
{
    case FichajeFueraDeRango = 'fichaje_fuera_de_rango';
    case AusenciaJornada = 'ausencia_jornada';
    case RetrasoJornada = 'retraso_jornada';

    public function label(): string
    {
        return match ($this) {
            self::FichajeFueraDeRango => 'Fichaje fuera de rango',
            self::AusenciaJornada => 'Ausencia de jornada',
            self::RetrasoJornada => 'Retraso de jornada',
        };
    }
}
