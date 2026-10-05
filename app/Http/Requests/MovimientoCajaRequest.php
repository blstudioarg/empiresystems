<?php

namespace App\Http\Requests;

use App\Models\CajaMovimiento;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Entrada o salida manual de efectivo (feature 048, FR-007): importe > 0 y motivo obligatorio. */
class MovimientoCajaRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('motivo'))) {
            $this->merge(['motivo' => trim($this->input('motivo'))]);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'tipo' => ['required', Rule::in([CajaMovimiento::TIPO_ENTRADA, CajaMovimiento::TIPO_SALIDA])],
            'importe' => ['required', 'numeric', 'gt:0', 'max:99999.99'],
            'motivo' => ['required', 'string', 'max:160'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'importe.gt' => __('El importe tiene que ser mayor que cero.'),
            'motivo.required' => __('Escribe el motivo del movimiento.'),
        ];
    }
}
