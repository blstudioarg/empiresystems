<?php

namespace App\Rules;

use App\Support\ValidadorIdentificacionFiscal;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class NifEspanol implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $value = trim((string) $value);

        if ($value === '') {
            return;
        }

        if (! ValidadorIdentificacionFiscal::esValido($value)) {
            $fail('El :attribute no tiene un formato válido de NIF, NIE o CIF.');
        }
    }
}
