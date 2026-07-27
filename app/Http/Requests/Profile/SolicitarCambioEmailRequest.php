<?php

namespace App\Http\Requests\Profile;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Validator;

class SolicitarCambioEmailRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'contrasena_actual' => ['required', 'string'],
            'nuevo_email' => ['required', 'email', 'max:255'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $user = $this->user();

            if ($this->filled('contrasena_actual')
                && ! Hash::check($this->input('contrasena_actual'), $user->password)) {
                $validator->errors()->add('contrasena_actual', 'La contraseña actual no es correcta.');
            }

            if ($this->filled('nuevo_email')) {
                $nuevoEmail = $this->input('nuevo_email');

                // Único a nivel del tenant activo entre `email` y `pending_email` de otros
                // usuarios (data-model.md): no se permite reservar un correo que otro usuario
                // del tenant ya usa o tiene pendiente.
                $enUso = User::where('tenant_id', $user->tenant_id)
                    ->where('id', '!=', $user->id)
                    ->where(function ($query) use ($nuevoEmail) {
                        $query->where('email', $nuevoEmail)->orWhere('pending_email', $nuevoEmail);
                    })
                    ->exists();

                if ($enUso) {
                    $validator->errors()->add('nuevo_email', 'Ese correo ya está en uso en tu empresa.');
                }
            }
        });
    }
}
