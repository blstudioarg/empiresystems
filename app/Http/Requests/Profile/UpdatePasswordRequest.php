<?php

namespace App\Http\Requests\Profile;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Validator;

class UpdatePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'contrasena_actual' => ['required', 'string'],
            'password' => ['required', 'confirmed', 'min:8'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($this->filled('contrasena_actual')
                && ! Hash::check($this->input('contrasena_actual'), $this->user()->password)) {
                $validator->errors()->add('contrasena_actual', 'La contraseña actual no es correcta.');
            }
        });
    }
}
