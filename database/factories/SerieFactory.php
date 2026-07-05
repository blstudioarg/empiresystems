<?php

namespace Database\Factories;

use App\Enums\TipoFactura;
use App\Models\Serie;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Serie>
 */
class SerieFactory extends Factory
{
    protected $model = Serie::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'codigo' => 'F',
            'tipo' => TipoFactura::Ordinaria,
            'ejercicio' => null,
            'proximo_numero' => 1,
            'formato' => '{serie}-{anio}-{numero:0000}',
            'activa' => true,
        ];
    }

    public function rectificativa(): static
    {
        return $this->state(fn (array $attributes) => [
            'codigo' => 'R',
            'tipo' => TipoFactura::Rectificativa,
        ]);
    }

    public function simplificada(): static
    {
        return $this->state(fn (array $attributes) => [
            'codigo' => 'S',
            'tipo' => TipoFactura::Simplificada,
        ]);
    }
}
