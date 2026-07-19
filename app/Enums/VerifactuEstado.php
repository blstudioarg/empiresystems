<?php

namespace App\Enums;

enum VerifactuEstado: string
{
    case Pendiente = 'pendiente';
    case Registrada = 'registrada';
    case Enviada = 'enviada';
    case Error = 'error';
}
