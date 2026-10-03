<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            // Único dentro del tenant del dominio, no en toda la plataforma: la misma persona
            // puede estar dada de alta en varias empresas con el mismo correo.
            'email' => [
                'required',
                'email',
                Rule::unique('users', 'email')->where(
                    fn ($query) => $query->where('tenant_id', tenant('id'))
                ),
            ],
            'password' => ['required', 'confirmed', 'min:8'],
        ];
    }
}
