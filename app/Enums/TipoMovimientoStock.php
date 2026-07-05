<?php

namespace App\Enums;

enum TipoMovimientoStock: string
{
    case Entrada = 'entrada';
    case Salida = 'salida';
    case Ajuste = 'ajuste';
}
