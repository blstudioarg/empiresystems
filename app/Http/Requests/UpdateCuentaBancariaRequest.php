<?php

namespace App\Http\Requests;

use App\Rules\IbanValido;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCuentaBancariaRequest extends FormRequest
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
            // El banco debe pertenecer al tenant activo (los bancos son tenant-dependientes).
            'banco_id' => ['required', Rule::exists('bancos', 'id')->where('tenant_id', tenant()?->getTenantKey())],
            'alias' => ['required', 'string', 'max:255'],
            'iban' => ['required', 'string', new IbanValido],
            'titular' => ['required', 'string', 'max:255'],
        ];
    }
}
