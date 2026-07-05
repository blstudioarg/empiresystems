<?php

namespace Database\Seeders;

use App\Models\Serie;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

class SerieSeeder extends Seeder
{
    /**
     * Nota: desde la corrección del 2026-07-04, `Tenant::booted()` ya siembra las tres series por
     * defecto (`Tenant::seriesPorDefecto()`) automáticamente para todo tenant nuevo. Este seeder
     * queda como red de seguridad idempotente para el tenant demo (p. ej. si viene de datos
     * anteriores a la corrección) y reutiliza el mismo catálogo para no duplicar los valores.
     */
    public function run(): void
    {
        $tenant = Tenant::where('nombre_comercial', 'Empresa Demo SL')->first();

        if (! $tenant) {
            return;
        }

        foreach (Tenant::seriesPorDefecto() as $codigo => $atributos) {
            Serie::firstOrCreate(
                ['tenant_id' => $tenant->id, 'codigo' => $codigo, 'ejercicio' => null],
                $atributos
            );
        }
    }
}
