<?php

namespace Database\Seeders;

use App\Models\Serie;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

class SerieSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::where('nombre_comercial', 'Empresa Demo SL')->first();

        if (! $tenant) {
            return;
        }

        Serie::firstOrCreate(
            ['tenant_id' => $tenant->id, 'codigo' => 'F', 'ejercicio' => null],
            [
                'tipo' => 'ordinaria',
                'proximo_numero' => 1,
                'formato' => '{serie}-{anio}-{numero:0000}',
                'activa' => true,
            ]
        );

        Serie::firstOrCreate(
            ['tenant_id' => $tenant->id, 'codigo' => 'R', 'ejercicio' => null],
            [
                'tipo' => 'rectificativa',
                'proximo_numero' => 1,
                'formato' => '{serie}-{anio}-{numero:0000}',
                'activa' => true,
            ]
        );
    }
}
