<?php

namespace Database\Seeders;

use App\Models\Articulo;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

class ArticuloSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::where('nombre_comercial', 'Empresa Demo SL')->first();

        if (! $tenant) {
            return;
        }

        Articulo::firstOrCreate(
            ['tenant_id' => $tenant->id, 'sku' => 'PROD-001'],
            [
                'tipo' => 'producto',
                'nombre' => 'Caja de cartón reforzada',
                'descripcion' => 'Caja de embalaje reforzada, tamaño estándar.',
                'unidad' => 'ud',
                'precio' => 3.5,
                'tipo_impositivo' => 21,
                'gestion_stock' => true,
                'stock_actual' => 150,
                'stock_minimo' => 20,
            ]
        );

        Articulo::firstOrCreate(
            ['tenant_id' => $tenant->id, 'sku' => 'PROD-002'],
            [
                'tipo' => 'producto',
                'nombre' => 'Cinta adhesiva de embalaje',
                'descripcion' => 'Rollo de cinta adhesiva transparente, 50m.',
                'unidad' => 'ud',
                'precio' => 1.8,
                'tipo_impositivo' => 21,
                'gestion_stock' => false,
            ]
        );

        Articulo::firstOrCreate(
            ['tenant_id' => $tenant->id, 'sku' => 'SERV-001'],
            [
                'tipo' => 'servicio',
                'nombre' => 'Consultoría de logística',
                'descripcion' => 'Servicio de asesoría en optimización de procesos logísticos.',
                'unidad' => 'hora',
                'precio' => 45,
                'tipo_impositivo' => 21,
            ]
        );
    }
}
