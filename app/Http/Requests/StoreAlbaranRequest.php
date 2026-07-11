<?php

namespace App\Http\Requests;

use App\Models\Cliente;
use App\Models\Presupuesto;
use App\Rules\TipoImpositivoValido;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAlbaranRequest extends FormRequest
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
            'presupuesto_id' => ['nullable', Rule::exists(Presupuesto::class, 'id')->where('tenant_id', tenant()->id)],
            'cliente_id' => ['nullable', Rule::exists(Cliente::class, 'id')->where('tenant_id', tenant()->id)],
            'notas' => ['nullable', 'string'],
            'lineas' => ['required', 'array', 'min:1'],
            'lineas.*.presupuesto_linea_id' => ['nullable', 'integer'],
            'lineas.*.articulo_id' => ['nullable', 'integer'],
            'lineas.*.concepto' => ['required', 'string', 'max:255'],
            'lineas.*.unidad' => ['nullable', 'string', 'max:20'],
            'lineas.*.cantidad' => ['required', 'numeric', 'min:0.0001'],
            'lineas.*.precio_unitario' => ['required', 'numeric', 'min:0'],
            'lineas.*.descuento_porcentaje' => ['nullable', 'numeric', 'between:0,100'],
            'lineas.*.tipo_impositivo' => ['required', 'numeric', 'between:0,100', new TipoImpositivoValido],
        ];
    }

    public function withValidator(ValidatorContract $validator): void
    {
        $validator->after(function (ValidatorContract $validator) {
            if (! $this->input('presupuesto_id') && ! $this->input('cliente_id')) {
                $validator->errors()->add('cliente_id', 'Debes indicar un cliente o un presupuesto de origen.');
            }

            if ($this->input('presupuesto_id')) {
                $presupuesto = Presupuesto::find($this->input('presupuesto_id'));

                if ($presupuesto && $presupuesto->estado->value !== 'aceptado') {
                    $validator->errors()->add('presupuesto_id', 'El presupuesto de origen debe estar aceptado.');
                }
            }
        });
    }
}
