<?php

namespace App\Http\Requests\Concerns;

trait ArticuloValidationMessages
{
    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'tipo.required' => 'El tipo de artículo es obligatorio.',
            'nombre.required' => 'El nombre es obligatorio.',
            'precio.required' => 'El precio es obligatorio.',
            'precio.min' => 'El precio no puede ser negativo.',
            'tipo_impositivo.required' => 'El tipo impositivo es obligatorio.',
            'tipo_impositivo.between' => 'El tipo impositivo debe estar entre 0 y 100.',
            'stock_actual.required' => 'El stock actual es obligatorio cuando se gestiona el stock del producto.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'tipo' => 'tipo de artículo',
            'sku' => 'SKU',
            'nombre' => 'nombre',
            'descripcion' => 'descripción',
            'unidad' => 'unidad',
            'precio' => 'precio',
            'tipo_impositivo' => 'tipo impositivo',
            'gestion_stock' => 'gestión de stock',
            'stock_actual' => 'stock actual',
            'stock_minimo' => 'stock mínimo',
            'aplica_recargo_equivalencia' => 'recargo de equivalencia',
        ];
    }
}
