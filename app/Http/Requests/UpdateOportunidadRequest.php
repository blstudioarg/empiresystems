<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOportunidadRequest extends FormRequest
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
            'titulo' => ['required', 'string', 'max:150'],
            'importe_estimado' => ['nullable', 'numeric', 'min:0'],
            'asignado_a' => ['nullable', 'integer'],
            'notas' => ['nullable', 'string'],
        ];
    }
}
