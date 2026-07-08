<?php

namespace App\Http\Requests;

use App\Models\Cliente;
use App\Models\Lead;
use App\Models\Oportunidad;
use App\Rules\TipoImpositivoValido;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePresupuestoRequest extends FormRequest
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
            'oportunidad_id' => ['nullable', Rule::exists(Oportunidad::class, 'id')->where('tenant_id', tenant()->id)],
            'cliente_id' => ['nullable', Rule::exists(Cliente::class, 'id')->where('tenant_id', tenant()->id)],
            'lead_id' => ['nullable', Rule::exists(Lead::class, 'id')->where('tenant_id', tenant()->id)],
            'fecha_emision' => ['required', 'date'],
            'fecha_validez' => ['nullable', 'date', 'after_or_equal:fecha_emision'],
            'irpf_porcentaje' => ['nullable', 'numeric', 'between:0,100'],
            'notas' => ['nullable', 'string'],
            'lineas' => ['required', 'array', 'min:1'],
            'lineas.*.articulo_id' => ['nullable', 'integer'],
            'lineas.*.concepto' => ['required', 'string', 'max:255'],
            'lineas.*.unidad' => ['nullable', 'string', 'max:20'],
            'lineas.*.cantidad' => ['required', 'numeric', 'min:0'],
            'lineas.*.precio_unitario' => ['required', 'numeric', 'min:0'],
            'lineas.*.descuento_porcentaje' => ['nullable', 'numeric', 'between:0,100'],
            'lineas.*.tipo_impositivo' => ['required', 'numeric', 'between:0,100', new TipoImpositivoValido],
        ];
    }

    public function withValidator(ValidatorContract $validator): void
    {
        $validator->after(function (ValidatorContract $validator) {
            if (! $this->input('cliente_id') && ! $this->input('lead_id')) {
                $validator->errors()->add('cliente_id', 'Debes indicar un cliente o un lead como receptor.');
            }
        });
    }
}
