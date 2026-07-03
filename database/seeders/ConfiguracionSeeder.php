<?php

namespace Database\Seeders;

use App\Models\Configuracion;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

class ConfiguracionSeeder extends Seeder
{
    /**
     * Valores de configuración por defecto del tenant demo. Usamos firstOrCreate por
     * (tenant_id, clave) para no pisar valores ya modificados por el usuario en re-seeds.
     */
    public function run(): void
    {
        $tenant = Tenant::firstWhere('nombre_comercial', 'Empresa Demo SL');

        if (! $tenant) {
            return;
        }

        $configuraciones = [
            [
                'clave' => 'apariencia.color_topbar',
                'valor' => '#1f2025',
                'tipo' => 'string',
                'grupo' => 'apariencia',
                'descripcion' => null,
            ],
        ];

        foreach ($configuraciones as $configuracion) {
            Configuracion::firstOrCreate(
                ['tenant_id' => $tenant->id, 'clave' => $configuracion['clave']],
                $configuracion
            );
        }
    }
}
