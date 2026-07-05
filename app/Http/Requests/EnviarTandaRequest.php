<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EnviarTandaRequest extends FormRequest
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
            'destinatario_ids' => ['required', 'array', 'min:1', 'max:8'],
            'destinatario_ids.*' => ['integer'],
        ];
    }
}
