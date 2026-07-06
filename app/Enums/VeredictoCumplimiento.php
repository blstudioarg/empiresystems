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

    /**
     * Clase CSS del calendario de fichajes (feature 026, D6). Mapa único en backend para que
     * los eventos del feed y la leyenda de la vista no diverjan; los estilos viven en
     * `public/css/app-overrides.css`.
     */
    public function clase(): string
    {
        return 'cal-veredicto-'.$this->value;
    }
}
