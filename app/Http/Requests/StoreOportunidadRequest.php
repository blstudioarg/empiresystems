<?php

namespace App\Http\Requests;

use App\Models\Cliente;
use App\Models\Lead;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOportunidadRequest extends FormRequest
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
            'titulo' => ['required', 'string', 'max:150'],
            'lead_id' => ['nullable', Rule::exists(Lead::class, 'id')->where('tenant_id', tenant()->id)],
            'cliente_id' => ['nullable', Rule::exists(Cliente::class, 'id')->where('tenant_id', tenant()->id)],
            'importe_estimado' => ['nullable', 'numeric', 'min:0'],
            'asignado_a' => ['nullable', 'integer'],
            'notas' => ['nullable', 'string'],
        ];
    }

    public function withValidator(ValidatorContract $validator): void
    {
        $validator->after(function (ValidatorContract $validator) {
            $leadId = $this->input('lead_id');
            $clienteId = $this->input('cliente_id');

            if ((! $leadId && ! $clienteId) || ($leadId && $clienteId)) {
                $validator->errors()->add('cliente_id', 'Indica exactamente un lead o un cliente (no ambos, no ninguno).');
            }
        });
    }
}
