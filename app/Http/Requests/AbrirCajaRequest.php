<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidaConteoCaja;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Abrir la caja (feature 048): fondo como importe **o** como conteo por denominaciones. Si llega el
 * conteo, el servidor calcula el fondo desde él y descarta cualquier total enviado (Principio III).
 */
class AbrirCajaRequest extends FormRequest
{
    use ValidaConteoCaja;

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'conteo' => ['nullable', ...$this->reglaConteo()],
            'fondo_inicial' => ['required_without:conteo', 'nullable', 'numeric', 'min:0', 'max:99999.99'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'fondo_inicial.required_without' => __('Indica con cuánto efectivo empieza la caja.'),
            'fondo_inicial.min' => __('El fondo no puede ser negativo.'),
            'fondo_inicial.max' => __('El fondo no puede superar 99.999,99 €.'),
        ];
    }
}
