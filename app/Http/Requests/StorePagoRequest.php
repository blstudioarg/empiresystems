<?php

namespace App\Http\Requests;

use App\Enums\FormaPago;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePagoRequest extends FormRequest
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
            'fecha' => ['required', 'date'],
            'importe' => ['required', 'numeric', 'gt:0'],
            'metodo' => ['required', Rule::enum(FormaPago::class)],
            'referencia' => ['nullable', 'string', 'max:100'],
        ];
    }
}
