<?php

namespace App\Http\Requests;

use App\Enums\TipoRectificacion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRectificativaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'tipo_rectificacion' => ['required', Rule::enum(TipoRectificacion::class)],
            'motivo_rectificacion' => ['required', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'tipo_rectificacion.required' => 'La modalidad de rectificación es obligatoria.',
            'motivo_rectificacion.required' => 'El motivo de la rectificación es obligatorio.',
        ];
    }
}
