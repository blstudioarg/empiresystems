<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCompraRequest extends FormRequest
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
            'proveedor_id' => [
                'required',
                Rule::exists('proveedores', 'id')->where(
                    fn ($query) => $query->where('tenant_id', tenant()->getTenantKey())->whereNull('deleted_at')
                ),
            ],
            'numero_documento' => ['nullable', 'string', 'max:255'],
            'fecha' => ['required', 'date'],
            'notas' => ['nullable', 'string'],
            'lineas' => ['required', 'array', 'min:1'],
            'lineas.*.articulo_id' => [
                'nullable',
                Rule::exists('articulos', 'id')->where(
                    fn ($query) => $query->where('tenant_id', tenant()->getTenantKey())->whereNull('deleted_at')
                ),
            ],
            'lineas.*.concepto' => ['required', 'string', 'max:255'],
            'lineas.*.unidad' => ['nullable', 'string', 'max:20'],
            'lineas.*.cantidad' => ['required', 'numeric', 'gt:0'],
            'lineas.*.precio_unitario' => ['required', 'numeric', 'min:0'],
            'lineas.*.tipo_impositivo' => ['required', 'numeric', 'min:0'],
        ];
    }
}
