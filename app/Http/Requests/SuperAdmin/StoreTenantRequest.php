<?php

namespace App\Http\Requests\SuperAdmin;

use App\Enums\RegimenImpositivo;
use App\Rules\NifEspanol;
use App\Support\DominioTenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTenantRequest extends FormRequest
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
            'dominio' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)*$/i',
                Rule::unique('domains', 'domain'),
            ],
            'nombre_comercial' => ['required', 'string', 'max:255'],
            'razon_social' => ['required', 'string', 'max:255'],
            'nif' => ['required', 'string', 'max:15', new NifEspanol],
            'direccion' => ['nullable', 'string', 'max:255'],
            'cp' => ['nullable', 'string', 'max:10'],
            'ciudad' => ['nullable', 'string', 'max:255'],
            'provincia' => ['nullable', 'string', 'max:255'],
            'pais' => ['required', 'string', 'size:2'],
            'regimen_impositivo' => ['required', Rule::enum(RegimenImpositivo::class)],
            'email' => ['required', 'email', 'max:255'],
            'activo' => ['boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'dominio' => DominioTenant::normalizar($this->input('dominio')),
            'pais' => $this->input('pais', 'ES'),
            'activo' => $this->has('activo') ? $this->boolean('activo') : true,
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'dominio.required' => 'El dominio es obligatorio.',
            'dominio.regex' => 'El dominio no tiene un formato de host válido.',
            'dominio.unique' => 'Ese dominio ya está en uso por otro tenant.',
            'nif.required' => 'El NIF es obligatorio.',
        ];
    }
}
