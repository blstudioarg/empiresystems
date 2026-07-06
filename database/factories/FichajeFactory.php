<?php

namespace Database\Factories;

use App\Enums\ResultadoUbicacionFichaje;
use App\Enums\TipoEventoFichaje;
use App\Models\Fichaje;
use App\Models\MiembroEquipo;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Fichaje>
 */
class FichajeFactory extends Factory
{
    protected $model = Fichaje::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'miembro_equipo_id' => MiembroEquipo::factory(),
            'tipo' => TipoEventoFichaje::Entrada,
            'ocurrido_at' => now(),
            'resultado_ubicacion' => ResultadoUbicacionFichaje::Dentro,
            'distancia_metros' => 10,
            'precision_metros' => 15,
            'corrige_fichaje_id' => null,
            'motivo' => null,
            'registrado_por' => null,
            'ip_origen' => fake()->ipv4(),
            'user_agent' => 'Mozilla/5.0 (Test)',
        ];
    }

    public function entrada(): static
    {
        return $this->state(fn (array $attributes) => ['tipo' => TipoEventoFichaje::Entrada]);
    }

    public function salida(): static
    {
        return $this->state(fn (array $attributes) => ['tipo' => TipoEventoFichaje::Salida]);
    }

    public function dentro(): static
    {
        return $this->state(fn (array $attributes) => [
            'resultado_ubicacion' => ResultadoUbicacionFichaje::Dentro,
            'distancia_metros' => 10,
        ]);
    }

    public function fuera(): static
    {
        return $this->state(fn (array $attributes) => [
            'resultado_ubicacion' => ResultadoUbicacionFichaje::Fuera,
            'distancia_metros' => 5000,
        ]);
    }

    public function sinUbicacion(): static
    {
        return $this->state(fn (array $attributes) => [
            'resultado_ubicacion' => ResultadoUbicacionFichaje::SinUbicacion,
            'distancia_metros' => null,
            'precision_metros' => null,
        ]);
    }
}
