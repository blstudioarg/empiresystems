<?php

namespace App\Http\Requests;

use App\Models\Carpeta;
use App\Support\ArchivosTenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreArchivoRequest extends FormRequest
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
            'archivo' => [
                'required',
                'file',
                'max:'.ArchivosTenant::limiteKb($tenantId),
                'extensions:'.implode(',', ArchivosTenant::EXTENSIONES_PERMITIDAS),
                'mimetypes:'.implode(',', ArchivosTenant::MIMES_PERMITIDOS),
            ],
            'carpeta_id' => ['nullable', Rule::exists(Carpeta::class, 'id')->where('tenant_id', $tenantId)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'archivo.max' => 'El archivo supera el límite de tamaño permitido.',
            'archivo.extensions' => 'Ese tipo de archivo no está permitido.',
            'archivo.mimetypes' => 'Ese tipo de archivo no está permitido.',
        ];
    }
}
