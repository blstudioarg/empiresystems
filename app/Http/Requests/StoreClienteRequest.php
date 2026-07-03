<?php

namespace App\Http\Requests;

use App\Enums\TipoCliente;
use App\Http\Requests\Concerns\ClienteValidationMessages;
use App\Rules\NifEspanol;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreClienteRequest extends FormRequest
{
    use ClienteValidationMessages;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $isEmpresa = $this->input('tipo') === TipoCliente::Empresa->value;

        return [
            'tipo' => ['required', Rule::enum(TipoCliente::class)],
            'nombre' => ['required', 'string', 'max:255'],
            'razon_social' => [$isEmpresa ? 'required' : 'nullable', 'string', 'max:255'],
            'nif' => [
                $isEmpresa ? 'required' : 'nullable',
                'string',
                'max:15',
                new NifEspanol,
                Rule::unique('clientes', 'nif')->where(
                    fn ($query) => $query->where('tenant_id', tenant()->getTenantKey())->whereNull('deleted_at')
                ),
            ],
            'direccion' => ['nullable', 'string', 'max:255'],
            'cp' => ['nullable', 'string', 'max:10'],
            'ciudad' => ['nullable', 'string', 'max:255'],
            'provincia' => ['nullable', 'string', 'max:255'],
            'pais' => ['required', 'string', 'size:2'],
            'email' => ['nullable', 'email', 'max:255'],
            'telefono' => ['nullable', 'string', 'max:30'],
            'aplica_recargo_equivalencia' => ['boolean'],
            'notas' => ['nullable', 'string'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'pais' => $this->input('pais', 'ES'),
            'aplica_recargo_equivalencia' => $this->boolean('aplica_recargo_equivalencia'),
        ]);
    }
}
