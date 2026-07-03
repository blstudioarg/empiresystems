<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class NifEspanol implements ValidationRule
{
    private const DNI_LETTERS = 'TRWAGMYFPDXBNJZSQVHLCKE';

    private const CIF_ORG_LETTERS = 'ABCDEFGHJNPQRSUVW';

    private const CIF_CONTROL_LETTERS = 'JABCDEFGHI';

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $value = strtoupper(trim((string) $value));

        if ($value === '') {
            return;
        }

        if (! $this->isValidDni($value) && ! $this->isValidNie($value) && ! $this->isValidCif($value)) {
            $fail('El :attribute no tiene un formato válido de NIF, NIE o CIF.');
        }
    }

    private function isValidDni(string $value): bool
    {
        if (! preg_match('/^(\d{8})([A-Z])$/', $value, $matches)) {
            return false;
        }

        $number = (int) $matches[1];
        $letter = $matches[2];

        return self::DNI_LETTERS[$number % 23] === $letter;
    }

    private function isValidNie(string $value): bool
    {
        if (! preg_match('/^([XYZ])(\d{7})([A-Z])$/', $value, $matches)) {
            return false;
        }

        $prefixMap = ['X' => '0', 'Y' => '1', 'Z' => '2'];
        $number = (int) ($prefixMap[$matches[1]].$matches[2]);
        $letter = $matches[3];

        return self::DNI_LETTERS[$number % 23] === $letter;
    }

    private function isValidCif(string $value): bool
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

            // Posiciones 1,3,5 (índice par) se duplican y se suman los dígitos del resultado;
            // posiciones 2,4,6 (índice impar) se suman directas.
            if ($position % 2 === 0) {
                $doubled = $digit * 2;
                $sumEven += intdiv($doubled, 10) + ($doubled % 10);
            } else {
                $sumOdd += $digit;
            }
        }

        $totalSum = $sumEven + $sumOdd;
        $controlDigit = (10 - ($totalSum % 10)) % 10;

        // Letras que exigen control numérico, letras que exigen control alfabético,
        // y letras que aceptan cualquiera de las dos formas.
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
