<?php

namespace App\Support;

/**
 * Validación de formato y dígito de control de NIF (persona física), NIE y CIF (persona
 * jurídica/entidad) españoles. Lógica pura, sin dependencias (docs/02-facturacion-espana.md §4).
 * Compartida por `App\Rules\NifEspanol` (validación de formularios) y el gate de generación de
 * Facturae (FR-021).
 */
class ValidadorIdentificacionFiscal
{
    private const DNI_LETTERS = 'TRWAGMYFPDXBNJZSQVHLCKE';

    private const CIF_ORG_LETTERS = 'ABCDEFGHJNPQRSUVW';

    private const CIF_CONTROL_LETTERS = 'JABCDEFGHI';

    public static function esValido(?string $value): bool
    {
        $value = strtoupper(trim((string) $value));

        if ($value === '') {
            return false;
        }

        return self::esDniValido($value) || self::esNieValido($value) || self::esCifValido($value);
    }

    public static function esDniValido(string $value): bool
    {
        if (! preg_match('/^(\d{8})([A-Z])$/', $value, $matches)) {
            return false;
        }

        $number = (int) $matches[1];
        $letter = $matches[2];

        return self::DNI_LETTERS[$number % 23] === $letter;
    }

    public static function esNieValido(string $value): bool
    {
        if (! preg_match('/^([XYZ])(\d{7})([A-Z])$/', $value, $matches)) {
            return false;
        }

        $prefixMap = ['X' => '0', 'Y' => '1', 'Z' => '2'];
        $number = (int) ($prefixMap[$matches[1]].$matches[2]);
        $letter = $matches[3];

        return self::DNI_LETTERS[$number % 23] === $letter;
    }

    public static function esCifValido(string $value): bool
    {
        if (! preg_match('/^([A-Z])(\d{7})([0-9A-Z])$/', $value, $matches)) {
            return false;
        }

        $orgLetter = $matches[1];
        $digits = $matches[2];
        $control = $matches[3];

        if (! str_contains(self::CIF_ORG_LETTERS, $orgLetter)) {
            return false;
        }

        $sumEven = 0;
        $sumOdd = 0;

        foreach (str_split($digits) as $position => $digit) {
            $digit = (int) $digit;

            if ($position % 2 === 0) {
                $doubled = $digit * 2;
                $sumEven += intdiv($doubled, 10) + ($doubled % 10);
            } else {
                $sumOdd += $digit;
            }
        }

        $totalSum = $sumEven + $sumOdd;
        $controlDigit = (10 - ($totalSum % 10)) % 10;

        $numericOnly = ['A', 'B', 'E', 'H'];
        $letterOnly = ['K', 'P', 'Q', 'S'];

        if (in_array($orgLetter, $numericOnly, true)) {
            return $control === (string) $controlDigit;
        }

        if (in_array($orgLetter, $letterOnly, true)) {
            return $control === self::CIF_CONTROL_LETTERS[$controlDigit];
        }

        return $control === (string) $controlDigit || $control === self::CIF_CONTROL_LETTERS[$controlDigit];
    }
}
