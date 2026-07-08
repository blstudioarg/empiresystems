<?php

namespace Database\Factories;

use App\Enums\EstadoLead;
use App\Enums\OrigenLead;
use App\Models\Lead;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Lead>
 */
class LeadFactory extends Factory
{
    protected $model = Lead::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'nombre' => fake()->name(),
            'empresa' => fake()->optional()->company(),
            'email' => fake()->unique()->safeEmail(),
            'telefono' => fake()->phoneNumber(),
            'estado' => EstadoLead::Nuevo,
            'origen' => OrigenLead::Manual,
            'asignado_a' => null,
            'convertido_a_cliente_id' => null,
            'motivo_descarte' => null,
            'notas' => null,
        ];
    }

    public function descartado(): static
    {
        return $this->state(fn (array $attributes) => [
            'estado' => EstadoLead::Descartado,
            'motivo_descarte' => fake()->sentence(),
        ]);
    }

    public function convertido(): static
    {
        return $this->state(fn (array $attributes) => [
            'estado' => EstadoLead::Convertido,
        ]);
    }
}
