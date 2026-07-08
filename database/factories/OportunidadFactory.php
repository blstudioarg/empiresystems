<?php

namespace Database\Factories;

use App\Enums\EtapaOportunidad;
use App\Models\Cliente;
use App\Models\Oportunidad;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Oportunidad>
 */
class OportunidadFactory extends Factory
{
    protected $model = Oportunidad::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $tenant = Tenant::factory();

        return [
            'tenant_id' => $tenant,
            'titulo' => fake()->catchPhrase(),
            'lead_id' => null,
            'cliente_id' => Cliente::factory()->for($tenant, 'tenant'),
            'etapa' => EtapaOportunidad::Nueva,
            'importe_estimado' => fake()->randomFloat(2, 100, 10000),
            'asignado_a' => null,
            'motivo_perdida' => null,
            'cerrada_at' => null,
            'notas' => null,
        ];
    }

    public function ganada(): static
    {
        return $this->state(fn (array $attributes) => [
            'etapa' => EtapaOportunidad::Ganada,
            'cerrada_at' => now(),
        ]);
    }

    public function perdida(): static
    {
        return $this->state(fn (array $attributes) => [
            'etapa' => EtapaOportunidad::Perdida,
            'motivo_perdida' => fake()->sentence(),
            'cerrada_at' => now(),
        ]);
    }
}
