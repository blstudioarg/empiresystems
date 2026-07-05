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
     *
     * Excepción: el catálogo de partida idempotente (`firstOrCreate`) de `BancoSeeder` sí se
     * siembra aquí. Ya no es un catálogo global: los bancos son tenant-dependientes y solo se
     * siembran para el primer tenant (no-op si aún no existe ninguno), así que no pisa datos de
     * desarrollo.
     */
    public function run(): void
    {
        $this->call(BancoSeeder::class);
    }
}
