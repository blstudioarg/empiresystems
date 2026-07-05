<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCampanaRequest extends FormRequest
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
        $tenantId = tenant()->getTenantKey();

        return [
            'asunto' => ['required', 'string', 'max:255'],
            'cuerpo' => ['required', 'string'],
            'plantilla_email_id' => [
                'nullable',
                Rule::exists('plantillas_email', 'id')->where(
                    fn ($query) => $query->where('tenant_id', $tenantId)->whereNull('deleted_at')
                ),
            ],
            'cliente_ids' => ['required', 'array', 'min:1'],
            'cliente_ids.*' => [
                Rule::exists('clientes', 'id')->where(
                    fn ($query) => $query->where('tenant_id', $tenantId)->whereNull('deleted_at')
                ),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'cliente_ids.required' => 'Selecciona al menos un cliente destinatario.',
            'cliente_ids.min' => 'Selecciona al menos un cliente destinatario.',
            'cliente_ids.*.exists' => 'Uno de los clientes seleccionados no es válido.',
        ];
    }
}
