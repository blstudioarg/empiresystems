<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEmailRequest extends FormRequest
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
            'smtp_host' => ['required', 'string', 'max:255'],
            'smtp_port' => ['required', 'integer', 'between:1,65535'],
            'smtp_encryption' => ['required', 'string', 'in:ssl,tls'],
            'smtp_usuario' => ['required', 'string', 'max:255'],
            'smtp_password' => ['nullable', 'string'],
            'remitente' => ['required', 'email'],
            'remitente_nombre' => ['nullable', 'string', 'max:255'],
            'responder_a' => ['nullable', 'email'],
        ];
    }
}
