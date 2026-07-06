<?php

namespace Database\Factories;

use App\Enums\EstadoAlerta;
use App\Enums\TipoAlerta;
use App\Models\Alerta;
use App\Models\Fichaje;
use App\Models\MiembroEquipo;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Alerta>
 */
class AlertaFactory extends Factory
{
    protected $model = Alerta::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'miembro_equipo_id' => MiembroEquipo::factory(),
            'fichaje_id' => Fichaje::factory(),
            'tipo' => TipoAlerta::FichajeFueraDeRango,
            'distancia_metros' => 5000,
            'estado' => EstadoAlerta::Nueva,
            'resuelta_por' => null,
            'resuelta_at' => null,
        ];
    }

    public function resuelta(): static
    {
        return $this->state(fn (array $attributes) => [
            'estado' => EstadoAlerta::Resuelta,
            'resuelta_at' => now(),
        ]);
    }
}
