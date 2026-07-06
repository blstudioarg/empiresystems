<?php

namespace App\Enums;

enum PresetRango: string
{
    case Mes = 'mes';
    case Trimestre = 'trimestre';
    case Anio = 'anio';
    case Personalizado = 'personalizado';
}
