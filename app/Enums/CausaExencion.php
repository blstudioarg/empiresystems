<?php

namespace App\Enums;

/**
 * Causa de exención del IVA según el catálogo AEAT/Facturae (docs/02 §6). Cada código mapea a un
 * artículo de la Ley 37/1992 (LIVA).
 */
enum CausaExencion: string
{
    case E1 = 'E1';
    case E2 = 'E2';
    case E3 = 'E3';
    case E4 = 'E4';
    case E5 = 'E5';
    case E6 = 'E6';

    public function articulo(): string
    {
        return match ($this) {
            self::E1 => 'art. 20 LIVA',
            self::E2 => 'art. 21 LIVA',
            self::E3 => 'art. 22 LIVA',
            self::E4 => 'arts. 23 y 24 LIVA',
            self::E5 => 'art. 25 LIVA',
            self::E6 => 'otra exención',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::E1 => 'Exenta por art. 20 (operaciones interiores)',
            self::E2 => 'Exenta por art. 21 (exportaciones)',
            self::E3 => 'Exenta por art. 22 (operaciones asimiladas a exportaciones)',
            self::E4 => 'Exenta por arts. 23 y 24 (zonas francas, depósitos)',
            self::E5 => 'Exenta por art. 25 (entregas intracomunitarias)',
            self::E6 => 'Exenta por otra causa',
        };
    }

    /**
     * Mención legal sugerida para el PDF/Facturae según la causa de exención (autogenerable).
     */
    public function mencionLegalSugerida(): string
    {
        return 'Operación exenta de IVA, '.$this->articulo().'.';
    }
}
