<?php

namespace App\Http\Requests;

use App\Enums\FormaPago;
use App\Http\Requests\Concerns\FacturaValidationMessages;
use App\Models\Cliente;
use App\Rules\TipoImpositivoValido;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFacturaRequest extends FormRequest
{
    use FacturaValidationMessages;

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
            'cliente_id' => ['required', Rule::exists(Cliente::class, 'id')->where('tenant_id', tenant()->id)],
            'cliente_nombre' => ['nullable', 'string', 'max:255'],
            'cliente_razon_social' => ['nullable', 'string', 'max:255'],
            'cliente_nif' => ['nullable', 'string', 'max:15'],
            'cliente_direccion' => ['nullable', 'string', 'max:255'],
            'cliente_cp' => ['nullable', 'string', 'max:10'],
            'cliente_ciudad' => ['nullable', 'string', 'max:255'],
            'cliente_provincia' => ['nullable', 'string', 'max:255'],
            'cliente_pais' => ['nullable', 'string', 'size:2'],
            'fecha_expedicion' => ['required', 'date'],
            'fecha_operacion' => ['nullable', 'date'],
            'fecha_vencimiento' => ['nullable', 'date', 'after_or_equal:fecha_expedicion'],
            'forma_pago' => ['required', Rule::enum(FormaPago::class)],
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
}
