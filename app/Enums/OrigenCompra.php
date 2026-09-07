<?php

namespace App\Enums;

/**
 * Origen de una compra: cómo entró al sistema. `facturae` = creada al importar un XML Facturae
 * recibido de un proveedor (feature 022). `documento` = creada desde un PDF/imagen interpretado
 * por IA y confirmado por el usuario (feature 044).
 */
enum OrigenCompra: string
{
    case Manual = 'manual';
    case Facturae = 'facturae';
    case Documento = 'documento';
    case Otro = 'otro';

    public function label(): string
    {
        return match ($this) {
            self::Manual => 'Manual',
            self::Facturae => 'Facturae',
            self::Documento => 'Documento (IA)',
            self::Otro => 'Otro',
        };
    }
}
