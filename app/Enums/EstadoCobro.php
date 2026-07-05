<?php

namespace App\Enums;

enum EstadoCobro: string
{
    case Pendiente = 'pendiente';
    case Parcial = 'parcial';
    case Cobrada = 'cobrada';
}
