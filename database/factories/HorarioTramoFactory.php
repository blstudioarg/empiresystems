<?php

namespace Database\Factories;

use App\Models\Horario;
use App\Models\HorarioTramo;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HorarioTramo>
 */
class HorarioTramoFactory extends Factory
{
    protected $model = HorarioTramo::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'horario_id' => Horario::factory(),
            'dia_semana' => fake()->numberBetween(1, 5),
            'hora_inicio' => '09:00:00',
            'hora_fin' => '17:00:00',
        ];
    }
}
