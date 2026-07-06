<?php

namespace App\Enums;

/**
 * Calificación de la operación a efectos de Facturae/SII (menciones especiales, docs/02 §6).
 *
 * - S1: operación sujeta y no exenta, sin inversión del sujeto pasivo (caso normal).
 * - S2: operación sujeta con inversión del sujeto pasivo (ISP): no se repercute cuota, la
 *   declara el destinatario. La operación está SUJETA (no exenta) → sin causa de exención.
 * - N1: operación no sujeta por reglas de localización (art. 7 LIVA, no localizada en TAI).
 * - N2: operación no sujeta por otras reglas.
 */
enum CalificacionOperacion: string
{
    case S1 = 'S1';
    case S2 = 'S2';
    case N1 = 'N1';
    case N2 = 'N2';

    public function label(): string
    {
        return match ($this) {
            self::S1 => 'Sujeta y no exenta',
            self::S2 => 'Sujeta con inversión del sujeto pasivo',
            self::N1 => 'No sujeta (art. 7 LIVA)',
            self::N2 => 'No sujeta (otras reglas)',
        };
    }

    /**
     * Mención legal sugerida para el PDF/Facturae según la calificación (autogenerable).
     * Devuelve null cuando la calificación no exige mención específica (S1).
     */
    public function mencionLegalSugerida(): ?string
    {
        return match ($this) {
            self::S1 => null,
            self::S2 => 'Inversión del sujeto pasivo, art. 84.Uno.2º LIVA.',
            self::N1 => 'Operación no sujeta, art. 7 LIVA.',
            self::N2 => 'Operación no sujeta.',
        };
    }
}
