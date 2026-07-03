<?php

namespace App\Enums;

enum RegimenImpositivo: string
{
    case Iva = 'iva';
    case Igic = 'igic';
    case Ipsi = 'ipsi';
}
