<?php

namespace App\Ia\Tools;

use App\Models\Articulo;

/**
 * Tool de lectura: busca artículos del catálogo del tenant (US2).
 */
class BuscarArticulos extends ToolAsistente
{
    public function nombre(): string
    {
        return 'buscar_articulos';
    }

    public function descripcion(): string
    {
        return 'Busca artículos del catálogo por nombre o SKU. Devuelve precio y stock. Úsala cuando el usuario pregunte por productos/servicios o su disponibilidad.';
    }

    public function schema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'texto' => [
                    'type' => 'string',
                    'description' => 'Texto a buscar en nombre o SKU. Vacío para traer los más recientes.',
                ],
            ],
            'required' => [],
        ];
    }

    public function permisoRequerido(): string
    {
        return 'ver-articulos';
    }

    public function esLectura(): bool
    {
        return true;
    }

    public function ejecutar(array $parametros): array
    {
        $texto = trim((string) ($parametros['texto'] ?? ''));
        $limite = (int) config('ia.max_resultados_tool', 10);

        $query = Articulo::query()->latest('id');

        if ($texto !== '') {
            $query->where(function ($q) use ($texto) {
                $q->where('nombre', 'like', "%{$texto}%")
                    ->orWhere('sku', 'like', "%{$texto}%");
            });
        }

        $articulos = $query->limit($limite)->get(['id', 'sku', 'nombre', 'precio', 'gestion_stock', 'stock_actual', 'unidad']);

        return [
            'total' => $articulos->count(),
            'articulos' => $articulos->map(fn (Articulo $a) => [
                'id' => $a->id,
                'sku' => $a->sku,
                'nombre' => $a->nombre,
                'precio' => $a->precio,
                'unidad' => $a->unidad,
                'stock' => $a->gestion_stock ? $a->stock_actual : 'sin gestión de stock',
            ])->all(),
        ];
    }
}
