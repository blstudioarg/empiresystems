<?php

namespace App\Enums;

enum OrigenMovimientoStock: string
{
    case Factura = 'factura';
    case Compra = 'compra';
    case AjusteManual = 'ajuste_manual';
    case Inventario = 'inventario';
    case Devolucion = 'devolucion';
}
