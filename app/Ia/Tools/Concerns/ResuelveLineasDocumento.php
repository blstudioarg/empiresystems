<?php

namespace App\Ia\Tools\Concerns;

use App\Models\Articulo;
use App\Models\Cliente;
use Illuminate\Validation\ValidationException;

/**
 * Resolución de cliente + líneas para las tools de documentos (presupuesto/factura borrador).
 *
 * Los importes (precio unitario y tipo impositivo) se resuelven SIEMPRE desde el artículo en el
 * servidor, nunca desde parámetros del modelo (contrato de seguridad #5 / Principio III). El schema
 * de las tools solo acepta artículo + cantidad + descripción opcional.
 */
trait ResuelveLineasDocumento
{
    protected function resolverCliente(mixed $clienteId): Cliente
    {
        $cliente = Cliente::query()->find($clienteId);

        if ($cliente === null) {
            throw ValidationException::withMessages(['cliente_id' => 'No existe un cliente con ese ID.']);
        }

        return $cliente;
    }

    /**
     * @param  array<int, array<string, mixed>>  $lineasInput
     * @return array<int, array{articulo_id: int, concepto: string, unidad: ?string, cantidad: float, precio_unitario: float, tipo_impositivo: float}>
     */
    protected function resolverLineas(array $lineasInput): array
    {
        if ($lineasInput === []) {
            throw ValidationException::withMessages(['lineas' => 'El documento necesita al menos una línea.']);
        }

        $lineas = [];
        foreach ($lineasInput as $linea) {
            $articulo = Articulo::query()->find($linea['articulo_id'] ?? 0);

            if ($articulo === null) {
                throw ValidationException::withMessages(['lineas' => "No existe el artículo con ID {$linea['articulo_id']}."]);
            }

            $cantidad = (float) ($linea['cantidad'] ?? 0);
            if ($cantidad <= 0) {
                throw ValidationException::withMessages(['lineas' => "La cantidad de «{$articulo->nombre}» debe ser mayor que cero."]);
            }

            $lineas[] = [
                'articulo_id' => $articulo->id,
                'concepto' => $linea['descripcion'] ?? $articulo->nombre,
                'unidad' => $articulo->unidad,
                'cantidad' => $cantidad,
                'precio_unitario' => (float) $articulo->precio,
                'tipo_impositivo' => (float) $articulo->tipo_impositivo,
            ];
        }

        return $lineas;
    }

    /**
     * @param  array<int, array<string, mixed>>  $lineas
     */
    protected function resumenLineas(array $lineas): string
    {
        $partes = array_map(
            fn (array $l) => "{$l['cantidad']} × {$l['concepto']}",
            $lineas,
        );

        return implode('; ', $partes);
    }

    protected function schemaLineas(): array
    {
        return [
            'type' => 'array',
            'description' => 'Líneas del documento. El sistema toma del artículo su valor unitario y el impuesto automáticamente; no los indiques.',
            'items' => [
                'type' => 'object',
                'properties' => [
                    'articulo_id' => ['type' => 'integer'],
                    'cantidad' => ['type' => 'number'],
                    'descripcion' => ['type' => 'string', 'description' => 'Opcional; sobrescribe el nombre del artículo como concepto.'],
                ],
                'required' => ['articulo_id', 'cantidad'],
            ],
        ];
    }
}
