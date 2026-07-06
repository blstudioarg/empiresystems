<?php

namespace Database\Factories;

use App\Models\AsignacionHorario;
use App\Models\Horario;
use App\Models\MiembroEquipo;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AsignacionHorario>
 */
class AsignacionHorarioFactory extends Factory
{
    protected $model = AsignacionHorario::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'miembro_equipo_id' => MiembroEquipo::factory(),
            'horario_id' => Horario::factory(),
            'vigente_desde' => now()->subMonth()->toDateString(),
            'vigente_hasta' => null,
        ];
    }
}
