<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Valida un IBAN según ISO 13616: estructura (2 letras de país + 2 dígitos de control + BBAN
 * alfanumérico, hasta 34 caracteres) y dígito de control mediante el algoritmo mod-97 (ISO 7064).
 * Normaliza a mayúsculas y sin espacios antes de comprobar.
 */
class IbanValido implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail('El IBAN no es válido.');

            return;
        }

        $iban = strtoupper(preg_replace('/\s+/', '', $value));

        // Estructura general: 2 letras país + 2 dígitos control + 1..30 alfanuméricos (máx 34).
        if (! preg_match('/^[A-Z]{2}[0-9]{2}[A-Z0-9]{1,30}$/', $iban)) {
            $fail('El IBAN no es válido.');

            return;
        }

        // Reordenar: mover los 4 primeros caracteres al final.
        $reordenado = substr($iban, 4).substr($iban, 0, 4);

        // Convertir letras a números (A=10, B=11, ... Z=35).
        $numerico = '';
        foreach (str_split($reordenado) as $caracter) {
            $numerico .= ctype_alpha($caracter) ? (string) (ord($caracter) - 55) : $caracter;
        }

        // mod-97 por bloques para evitar overflow de enteros con IBAN largos.
        $resto = 0;
        foreach (str_split($numerico) as $digito) {
            $resto = ($resto * 10 + (int) $digito) % 97;
        }

        if ($resto !== 1) {
            $fail('El IBAN no es válido.');
        }
    }
}
