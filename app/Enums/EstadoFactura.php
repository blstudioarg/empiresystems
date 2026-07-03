<?php

namespace App\Enums;

enum EstadoFactura: string
{
    case Borrador = 'borrador';
    case Emitida = 'emitida';
    case Pagada = 'pagada';
    case Vencida = 'vencida';
    case Anulada = 'anulada';
    case Rectificada = 'rectificada';
}
