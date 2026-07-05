<?php

namespace Database\Factories;

use App\Models\PlantillaEmail;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlantillaEmail>
 */
class PlantillaEmailFactory extends Factory
{
    protected $model = PlantillaEmail::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'titulo' => fake()->sentence(3),
            'asunto' => fake()->sentence(5),
            'cuerpo' => '<p>'.fake()->paragraph().'</p>',
            'activa' => true,
        ];
    }

    public function inactiva(): static
    {
        return $this->state(fn (array $attributes) => ['activa' => false]);
    }
}
