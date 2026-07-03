<?php

namespace Database\Seeders;

use App\Models\Tenant;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // migrate:fresh borra los tenants/users con logo_path/avatar_path, pero no los
        // archivos subidos: limpiamos storage solo si la tabla de tenants está vacía (fresh
        // real), para no borrar imágenes de un tenant que ya existía y que el seed preserva
        // vía firstOrCreate.
        if (Tenant::query()->doesntExist()) {
            Storage::disk('public')->deleteDirectory('logos');
            Storage::disk('public')->deleteDirectory('avatars');
        }

        $this->call(ProvinciaLocalidadSeeder::class);
        $this->call(AuthSeeder::class);
        $this->call(ConfiguracionSeeder::class);
        $this->call(SerieSeeder::class);
        $this->call(ClienteSeeder::class);
        $this->call(ArticuloSeeder::class);
    }
}
