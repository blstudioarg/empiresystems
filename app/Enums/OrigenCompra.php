<?php

namespace App\Enums;

/**
 * Origen de una compra: cómo entró al sistema. `facturae` = creada al importar un XML Facturae
 * recibido de un proveedor (feature 022).
 */
enum OrigenCompra: string
{
    case Manual = 'manual';
    case Facturae = 'facturae';
    case Otro = 'otro';

    public function label(): string
    {
        return match ($this) {
            self::Manual => 'Manual',
            self::Facturae => 'Facturae',
            self::Otro => 'Otro',
        };
    }
}
