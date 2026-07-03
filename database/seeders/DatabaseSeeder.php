<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Intencionalmente vacío: `php artisan db:seed` / `migrate:fresh --seed` no deben tocar
     * datos de desarrollo ya existentes (riesgo de pisarlos/perderlos). Los seeders individuales
     * (`AuthSeeder`, `ClienteSeeder`, etc.) siguen disponibles para invocarse a mano con
     * `php artisan db:seed --class=NombreSeeder` cuando se necesiten explícitamente.
     */
    public function run(): void
    {
        //
    }
}
