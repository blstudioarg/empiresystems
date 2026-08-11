<?php

namespace Database\Factories;

use App\Models\PosMesa;
use App\Models\PosZona;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PosMesa> */
class PosMesaFactory extends Factory
{
    protected $model = PosMesa::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'tenant_id' => fn () => tenant()?->getTenantKey() ?? Tenant::factory(),
            'zona_id' => PosZona::factory(),
            'nombre' => fake()->unique()->numerify('Mesa ##'),
            'orden' => 0,
        ];
    }
}
