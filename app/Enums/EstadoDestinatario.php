<?php

namespace App\Enums;

enum EstadoDestinatario: string
{
    case Pendiente = 'pendiente';
    case Enviado = 'enviado';
    case Fallido = 'fallido';
}
