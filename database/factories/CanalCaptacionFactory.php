<?php

namespace Database\Factories;

use App\Models\CanalCaptacion;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CanalCaptacion>
 */
class CanalCaptacionFactory extends Factory
{
    protected $model = CanalCaptacion::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'nombre' => fake()->unique()->words(2, true),
            'activo' => true,
            'orden' => 0,
        ];
    }

    public function inactivo(): static
    {
        return $this->state(fn (array $attributes) => ['activo' => false]);
    }
}
