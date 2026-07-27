<?php

namespace Database\Seeders;

use App\Models\Articulo;
use App\Models\CategoriaArticulo;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

class CategoriaArticuloSeeder extends Seeder
{
    /**
     * Categorías del catálogo demo (SKUs PROD-004 a PROD-043, artículos tipo "restaurante"
     * sembrados a mano junto a sus fotos) agrupadas por tipo de plato, y asignación de cada
     * artículo a su categoría por SKU.
     */
    public function run(): void
    {
        $tenant = Tenant::where('nombre_comercial', 'BL Studio')->first();

        if (! $tenant) {
            return;
        }

        $categorias = [
            'Desayunos y meriendas' => [
                'PROD-004', 'PROD-006', 'PROD-007', 'PROD-015', 'PROD-016',
                'PROD-024', 'PROD-033', 'PROD-034', 'PROD-035',
            ],
            'Entrantes y para compartir' => [
                'PROD-005', 'PROD-017', 'PROD-022', 'PROD-025', 'PROD-026',
                'PROD-029', 'PROD-042', 'PROD-043', 'PROD-012',
            ],
            'Ensaladas y platos ligeros' => [
                'PROD-009', 'PROD-021',
            ],
            'Arroces y curries' => [
                'PROD-008', 'PROD-011', 'PROD-014', 'PROD-018', 'PROD-019',
            ],
            'Pastas' => [
                'PROD-020', 'PROD-027', 'PROD-028', 'PROD-032', 'PROD-036',
                'PROD-037', 'PROD-038', 'PROD-039',
            ],
            'Pizzas' => [
                'PROD-040', 'PROD-041',
            ],
            'Carnes y parrilla' => [
                'PROD-023', 'PROD-031',
            ],
            'Postres' => [
                'PROD-010', 'PROD-013', 'PROD-030',
            ],
        ];

        foreach ($categorias as $nombre => $skus) {
            $categoria = CategoriaArticulo::firstOrCreate(
                ['tenant_id' => $tenant->id, 'nombre' => $nombre]
            );

            Articulo::where('tenant_id', $tenant->id)
                ->whereIn('sku', $skus)
                ->update(['categoria_id' => $categoria->id]);
        }
    }
}
