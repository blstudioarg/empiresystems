<?php

namespace App\Enums;

enum FormaPago: string
{
    case Transferencia = 'transferencia';
    case Tarjeta = 'tarjeta';
    case Efectivo = 'efectivo';
    case Domiciliacion = 'domiciliacion';
}
