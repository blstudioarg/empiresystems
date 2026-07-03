<?php

namespace Database\Seeders;

use App\Models\Configuracion;
use App\Models\Tenant;
use App\Support\AparienciaTenant;
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
                'clave' => 'apariencia.color_primario',
                'valor' => AparienciaTenant::DEFAULT_PRIMARIO,
                'tipo' => 'string',
                'grupo' => 'apariencia',
                'descripcion' => null,
            ],
            [
                'clave' => 'apariencia.color_secundario',
                'valor' => AparienciaTenant::DEFAULT_SECUNDARIO,
                'tipo' => 'string',
                'grupo' => 'apariencia',
                'descripcion' => null,
            ],
            [
                'clave' => 'apariencia.color_topbar',
                'valor' => AparienciaTenant::DEFAULT_TOPBAR,
                'tipo' => 'string',
                'grupo' => 'apariencia',
                'descripcion' => null,
            ],
            [
                'clave' => 'apariencia.facebook_url',
                'valor' => AparienciaTenant::DEFAULT_FACEBOOK,
                'tipo' => 'string',
                'grupo' => 'apariencia',
                'descripcion' => null,
            ],
            [
                'clave' => 'apariencia.instagram_url',
                'valor' => AparienciaTenant::DEFAULT_INSTAGRAM,
                'tipo' => 'string',
                'grupo' => 'apariencia',
                'descripcion' => null,
            ],
            [
                'clave' => 'apariencia.titulo_login',
                'valor' => AparienciaTenant::DEFAULT_TITULO_LOGIN,
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
