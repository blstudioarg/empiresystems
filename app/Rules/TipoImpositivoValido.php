<?php

namespace App\Rules;

use App\Support\TiposImpositivos;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class TipoImpositivoValido implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $validos = TiposImpositivos::validosPara(tenant()->regimen_impositivo);

        if ($validos === null) {
            return;
        }

        $coincide = collect($validos)->contains(fn (float $valido) => abs($valido - (float) $value) < 0.001);

        if (! $coincide) {
            $fail('El tipo impositivo indicado no es válido para el régimen fiscal de tu empresa (IVA/IGIC/IPSI).');
        }
    }
}
