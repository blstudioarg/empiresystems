<?php

namespace App\Http\Requests;

use App\Enums\TipoArticulo;
use App\Models\Articulo;
use App\Models\Cliente;
use App\Rules\TipoImpositivoValido;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTicketRequest extends FormRequest
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
            'lineas' => ['required', 'array', 'min:1'],
            // Si la línea viene de un artículo del catálogo, debe ser un PRODUCTO del tenant:
            // un ticket de TPV no factura servicios (las líneas libres van con articulo_id nulo).
            'lineas.*.articulo_id' => [
                'nullable', 'integer',
                Rule::exists(Articulo::class, 'id')
                    ->where('tenant_id', tenant()->id)
                    ->where('tipo', TipoArticulo::Producto->value)
                    ->whereNull('deleted_at'),
            ],
            'lineas.*.concepto' => ['required', 'string', 'max:255'],
            'lineas.*.unidad' => ['nullable', 'string', 'max:20'],
            'lineas.*.cantidad' => ['required', 'numeric', 'gt:0'],
            'lineas.*.precio_unitario' => ['required', 'numeric', 'min:0'],
            'lineas.*.descuento_porcentaje' => ['nullable', 'numeric', 'between:0,100'],
            'lineas.*.tipo_impositivo' => ['required', 'numeric', 'between:0,100', new TipoImpositivoValido],

            // Receptor opcional (simplificada simple lo omite; cualificada lo informa).
            'receptor' => ['nullable', 'array'],
            'receptor.cliente_id' => ['nullable', Rule::exists(Cliente::class, 'id')->where('tenant_id', tenant()->id)],
            'receptor.cliente_nombre' => ['nullable', 'string', 'max:255'],
            'receptor.cliente_razon_social' => ['nullable', 'string', 'max:255'],
            // Para la cualificada exigimos al menos NIF + domicilio; se validan como par (uno obliga al otro).
            'receptor.cliente_nif' => ['nullable', 'required_with:receptor.cliente_direccion', 'string', 'max:15'],
            'receptor.cliente_direccion' => ['nullable', 'required_with:receptor.cliente_nif', 'string', 'max:255'],
            'receptor.cliente_cp' => ['nullable', 'string', 'max:10'],
            'receptor.cliente_ciudad' => ['nullable', 'string', 'max:255'],
            'receptor.cliente_provincia' => ['nullable', 'string', 'max:255'],
            'receptor.cliente_pais' => ['nullable', 'string', 'size:2'],

            'notas' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'lineas.required' => 'El ticket debe tener al menos una línea.',
            'lineas.min' => 'El ticket debe tener al menos una línea.',
            'lineas.*.articulo_id.exists' => 'Solo se pueden añadir productos a un ticket, no servicios.',
            'lineas.*.cantidad.gt' => 'La cantidad debe ser mayor que cero.',
            'receptor.cliente_nif.required_with' => 'Para una simplificada cualificada indique el NIF del receptor.',
            'receptor.cliente_direccion.required_with' => 'Para una simplificada cualificada indique el domicilio del receptor.',
        ];
    }
}
