<?php

namespace Database\Factories;

use App\Enums\EstadoDestinatario;
use App\Models\Campana;
use App\Models\CampanaDestinatario;
use App\Models\Cliente;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CampanaDestinatario>
 */
class CampanaDestinatarioFactory extends Factory
{
    protected $model = CampanaDestinatario::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'campana_id' => Campana::factory(),
            'cliente_id' => Cliente::factory(),
            'email' => fake()->safeEmail(),
            'estado' => EstadoDestinatario::Pendiente,
            'error' => null,
            'enviado_at' => null,
        ];
    }

    public function enviado(): static
    {
        return $this->state(fn (array $attributes) => [
            'estado' => EstadoDestinatario::Enviado,
            'enviado_at' => now(),
        ]);
    }

    public function fallido(): static
    {
        return $this->state(fn (array $attributes) => [
            'estado' => EstadoDestinatario::Fallido,
            'error' => 'Error de envío',
        ]);
    }
}
