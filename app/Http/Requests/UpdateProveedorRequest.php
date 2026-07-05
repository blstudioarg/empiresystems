<?php

namespace App\Http\Requests;

use App\Rules\NifEspanol;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProveedorRequest extends FormRequest
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
        $proveedor = $this->route('proveedor');

        return [
            'nombre' => ['nullable', 'string', 'max:255'],
            'razon_social' => ['nullable', 'string', 'max:255'],
            'nif' => [
                'nullable',
                'string',
                'max:15',
                new NifEspanol,
                Rule::unique('proveedores', 'nif')
                    ->where(fn ($query) => $query->where('tenant_id', tenant()->getTenantKey())->whereNull('deleted_at'))
                    ->ignore($proveedor),
            ],
            'direccion' => ['nullable', 'string', 'max:255'],
            'cp' => ['nullable', 'string', 'max:10'],
            'ciudad' => ['nullable', 'string', 'max:255'],
            'provincia' => ['nullable', 'string', 'max:255'],
            'pais' => ['required', 'string', 'size:2'],
            'email' => ['nullable', 'email', 'max:255'],
            'telefono' => ['nullable', 'string', 'max:30'],
            'notas' => ['nullable', 'string'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'pais' => $this->input('pais', 'ES'),
        ]);
    }
}
