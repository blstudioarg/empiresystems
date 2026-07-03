<?php

namespace App\Http\Requests\Concerns;

trait FacturaValidationMessages
{
    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'cliente_id.required' => 'El cliente es obligatorio.',
            'cliente_id.exists' => 'El cliente seleccionado no es válido.',
            'fecha_expedicion.required' => 'La fecha de expedición es obligatoria.',
            'fecha_vencimiento.after_or_equal' => 'La fecha de vencimiento no puede ser anterior a la de expedición.',
            'forma_pago.required' => 'La forma de pago es obligatoria.',
            'lineas.required' => 'La factura debe tener al menos una línea.',
            'lineas.min' => 'La factura debe tener al menos una línea.',
            'lineas.*.concepto.required' => 'El concepto de la línea es obligatorio.',
            'lineas.*.cantidad.min' => 'La cantidad no puede ser negativa.',
            'lineas.*.precio_unitario.min' => 'El precio no puede ser negativo.',
            'lineas.*.tipo_impositivo.between' => 'El tipo impositivo debe estar entre 0 y 100.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'cliente_id' => 'cliente',
            'fecha_expedicion' => 'fecha de expedición',
            'fecha_operacion' => 'fecha de operación',
            'fecha_vencimiento' => 'fecha de vencimiento',
            'forma_pago' => 'forma de pago',
            'irpf_porcentaje' => 'IRPF',
            'lineas' => 'líneas',
        ];
    }
}
