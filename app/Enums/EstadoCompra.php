<?php

namespace App\Enums;

enum EstadoCompra: string
{
    case Borrador = 'borrador';
    case Confirmada = 'confirmada';
    case Anulada = 'anulada';
}
