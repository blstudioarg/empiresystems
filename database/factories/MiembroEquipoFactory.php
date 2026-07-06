<?php

namespace Database\Factories;

use App\Models\MiembroEquipo;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MiembroEquipo>
 */
class MiembroEquipoFactory extends Factory
{
    protected $model = MiembroEquipo::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'user_id' => User::factory(),
            'puesto' => fake()->jobTitle(),
            'trabajo_direccion' => fake()->address(),
            'trabajo_latitud' => fake()->latitude(),
            'trabajo_longitud' => fake()->longitude(),
            'distancia_max_metros' => 100,
            'casa_direccion' => fake()->address(),
            'casa_latitud' => fake()->latitude(),
            'casa_longitud' => fake()->longitude(),
            'distancia_casa_trabajo_metros' => fake()->numberBetween(500, 20000),
            'activo' => true,
            'dado_baja_at' => null,
        ];
    }

    public function inactivo(): static
    {
        return $this->state(fn (array $attributes) => [
            'activo' => false,
            'dado_baja_at' => now(),
        ]);
    }
}
