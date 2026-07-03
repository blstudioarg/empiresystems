<?php

namespace App\Enums;

enum TipoFactura: string
{
    case Ordinaria = 'ordinaria';
    case Simplificada = 'simplificada';
    case Rectificativa = 'rectificativa';
}
