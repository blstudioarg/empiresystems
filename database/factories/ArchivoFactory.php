<?php

namespace Database\Factories;

use App\Models\Archivo;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Archivo>
 */
class ArchivoFactory extends Factory
{
    protected $model = Archivo::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $nombre = $this->faker->unique()->word().'.pdf';

        return [
            'tenant_id' => Tenant::factory(),
            'carpeta_id' => null,
            'nombre' => $nombre,
            'nombre_original' => $nombre,
            'ruta' => 'tenants/1/documentos/'.Str::uuid().'.pdf',
            'mime' => 'application/pdf',
            'extension' => 'pdf',
            'tamano' => $this->faker->numberBetween(1024, 1024 * 1024),
            'subido_por' => User::factory(),
        ];
    }
}
