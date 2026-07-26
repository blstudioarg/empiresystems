<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCanalCaptacionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->nombre)) {
            $this->merge(['nombre' => trim($this->nombre)]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $canal = $this->route('canal');

        $unica = Rule::unique('canales_captacion', 'nombre')->where('tenant_id', tenant()->id);

        if ($canal) {
            $unica->ignore(is_object($canal) ? $canal->id : $canal);
        }

        return [
            'nombre' => ['required', 'string', 'max:80', $unica],
            'activo' => ['sometimes', 'boolean'],
            'orden' => ['sometimes', 'integer'],
        ];
    }
}
