<?php

namespace App\Http\Requests;

use App\Models\Carpeta;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateArchivoRequest extends FormRequest
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
            'nombre' => ['sometimes', 'required', 'string', 'max:255'],
            'carpeta_id' => ['sometimes', 'nullable', Rule::exists(Carpeta::class, 'id')->where('tenant_id', $tenantId)],
        ];
    }
}
