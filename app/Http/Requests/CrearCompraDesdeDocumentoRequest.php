<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Reglas del alta manual (`StoreCompraRequest`) más lo propio de este flujo. **No acepta importes
 * del cliente**: `base`, `cuota_impuesto` y los totales que lleguen se ignoran porque los calcula
 * el servidor (Principio III).
 *
 * `proveedor_id` y `lineas.*.articulo_id` se comprueban contra el tenant activo, así que un id de
 * otra empresa es un fallo de validación y nunca un acceso (Principio I).
 */
class CrearCompraDesdeDocumentoRequest extends FormRequest
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
        $delTenant = fn (string $tabla) => Rule::exists($tabla, 'id')->where(
            fn ($query) => $query->where('tenant_id', tenant()->getTenantKey())->whereNull('deleted_at')
        );

        return [
            'crear_proveedor' => ['boolean'],

            // Requerido salvo que el usuario haya pedido dar de alta uno nuevo (FR-020).
            'proveedor_id' => ['required_if:crear_proveedor,false', 'nullable', $delTenant('proveedores')],

            'proveedor_nuevo' => ['required_if:crear_proveedor,true', 'nullable', 'array'],
            'proveedor_nuevo.nombre' => ['required_if:crear_proveedor,true', 'nullable', 'string', 'max:255'],
            'proveedor_nuevo.razon_social' => ['nullable', 'string', 'max:255'],
            'proveedor_nuevo.nif' => ['nullable', 'string', 'max:20'],
            'proveedor_nuevo.direccion' => ['nullable', 'string', 'max:255'],
            'proveedor_nuevo.cp' => ['nullable', 'string', 'max:10'],
            'proveedor_nuevo.ciudad' => ['nullable', 'string', 'max:255'],
            'proveedor_nuevo.provincia' => ['nullable', 'string', 'max:255'],
            'proveedor_nuevo.pais' => ['nullable', 'string', 'max:255'],
            'proveedor_nuevo.email' => ['nullable', 'email', 'max:255'],
            'proveedor_nuevo.telefono' => ['nullable', 'string', 'max:50'],

            'numero_documento' => ['nullable', 'string', 'max:255'],
            'fecha' => ['required', 'date'],
            'notas' => ['nullable', 'string'],
            'confirmar_duplicado' => ['boolean'],

            'lineas' => ['required', 'array', 'min:1'],
            'lineas.*.articulo_id' => ['nullable', $delTenant('articulos')],
            'lineas.*.concepto' => ['required', 'string', 'max:255'],
            'lineas.*.unidad' => ['nullable', 'string', 'max:20'],
            'lineas.*.cantidad' => ['required', 'numeric', 'gt:0'],
            'lineas.*.precio_unitario' => ['required', 'numeric', 'min:0'],
            'lineas.*.tipo_impositivo' => ['required', 'numeric', 'min:0'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'crear_proveedor' => $this->boolean('crear_proveedor'),
            'confirmar_duplicado' => $this->boolean('confirmar_duplicado'),
        ]);
    }

    public function messages(): array
    {
        return [
            'proveedor_id.required_if' => 'Elegí un proveedor o creá uno nuevo con los datos del documento.',
            'proveedor_nuevo.nombre.required_if' => 'El proveedor nuevo necesita al menos un nombre.',
        ];
    }
}
