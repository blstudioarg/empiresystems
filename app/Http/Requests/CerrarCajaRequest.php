<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidaConteoCaja;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Cerrar la caja (feature 048): la sesión que se contó + el conteo por denominaciones o el total
 * contado. Si llegan los dos, manda el conteo (el servidor recalcula el total desde él).
 */
class CerrarCajaRequest extends FormRequest
{
    use ValidaConteoCaja;

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('observacion'))) {
            $obs = trim($this->input('observacion'));
            $this->merge(['observacion' => $obs === '' ? null : $obs]);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'sesion_id' => ['required', 'integer'],
            'conteo' => ['nullable', ...$this->reglaConteo()],
            'efectivo_contado' => ['required_without:conteo', 'nullable', 'numeric', 'min:0', 'max:999999.99'],
            'observacion' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'efectivo_contado.required_without' => 'Cuenta el efectivo del cajón para poder cerrar.',
        ];
    }
}
