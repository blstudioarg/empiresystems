<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AsignacionHorarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'horario_id' => [
                'required',
                Rule::exists('horarios', 'id')
                    ->where('tenant_id', tenant()->getTenantKey())
                    ->where('activo', true),
            ],
            'vigente_desde' => ['required', 'date_format:Y-m-d'],
        ];
    }
}
