<?php

namespace App\Enums;

enum VeredictoCumplimiento: string
{
    case Libre = 'libre';
    case Ausencia = 'ausencia';
    case Retraso = 'retraso';
    case Parcial = 'parcial';
    case Cumplido = 'cumplido';
    case Exceso = 'exceso';

    public function label(): string
    {
        return match ($this) {
            self::Libre => 'Día libre',
            self::Ausencia => 'Ausencia',
            self::Retraso => 'Retraso',
            self::Parcial => 'Cumplimiento parcial',
            self::Cumplido => 'Cumplido',
            self::Exceso => 'Exceso de jornada',
        };
    }
}
