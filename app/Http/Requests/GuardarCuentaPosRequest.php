<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Guardar una cuenta abierta (feature 038).
 *
 * `version` es obligatoria: es el bloqueo optimista de FR-024. Sin ella no hay forma de detectar
 * que otro camarero tocó la misma cuenta desde otra tablet, y el último en guardar borraría el
 * trabajo del primero en silencio.
 *
 * Los importes que llegan aquí son **de referencia del catálogo**, no autoridad: el total y los
 * impuestos siempre se recalculan en servidor al cobrar (Principio III).
 */
class GuardarCuentaPosRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'version' => ['required', 'integer', 'min:1'],
            'mesa_id' => ['nullable', 'integer'],
            'comensales' => ['nullable', 'integer', 'min:1', 'max:255'],
            'notas' => ['nullable', 'string', 'max:2000'],

            'receptor' => ['nullable', 'array'],
            'receptor.cliente_id' => ['nullable', 'integer'],
            'receptor.cliente_nif' => ['nullable', 'string', 'max:15'],
            'receptor.cliente_nombre' => ['nullable', 'string', 'max:255'],
            'receptor.cliente_razon_social' => ['nullable', 'string', 'max:255'],
            'receptor.cliente_direccion' => ['nullable', 'string', 'max:255'],

            'lineas' => ['present', 'array'],
            'lineas.*.id' => ['nullable', 'integer'],
            'lineas.*.articulo_id' => ['nullable', 'integer'],
            'lineas.*.concepto' => ['required', 'string', 'max:255'],
            'lineas.*.unidad' => ['nullable', 'string', 'max:20'],
            'lineas.*.cantidad' => ['required', 'numeric', 'min:0.01'],
            'lineas.*.tipo_impositivo' => ['required', 'numeric', 'min:0', 'max:100'],

            'lineas.*.opciones' => ['nullable', 'array'],
            'lineas.*.opciones.*.opcion_id' => ['required', 'integer'],
        ];
    }
}
