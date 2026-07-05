<?php

namespace App\Http\Requests;

use App\Models\Carpeta;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCarpetaRequest extends FormRequest
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
            'nombre' => [
                'required',
                'string',
                'max:255',
                Rule::unique(Carpeta::class, 'nombre')
                    ->where('tenant_id', $tenantId)
                    ->where('parent_id', $this->input('parent_id'))
                    ->whereNull('deleted_at'),
            ],
            'parent_id' => ['nullable', Rule::exists(Carpeta::class, 'id')->where('tenant_id', $tenantId)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nombre.unique' => 'Ya existe una carpeta con ese nombre en este nivel.',
        ];
    }
}
