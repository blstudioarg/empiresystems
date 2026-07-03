<?php

namespace App\Enums;

enum TipoImpuesto: string
{
    case Iva = 'iva';
    case Igic = 'igic';
    case Ipsi = 'ipsi';
    case Recargo = 'recargo';
    case Irpf = 'irpf';
}
