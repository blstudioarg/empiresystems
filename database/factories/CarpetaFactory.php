<?php

namespace Database\Factories;

use App\Models\Carpeta;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Carpeta>
 */
class CarpetaFactory extends Factory
{
    protected $model = Carpeta::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'parent_id' => null,
            'nombre' => ucfirst($this->faker->unique()->word()),
        ];
    }
}
