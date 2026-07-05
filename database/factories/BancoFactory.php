<?php

namespace Database\Factories;

use App\Models\Banco;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Banco>
 */
class BancoFactory extends Factory
{
    protected $model = Banco::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'nombre' => fake()->unique()->company(),
        ];
    }
}
