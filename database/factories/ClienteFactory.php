<?php

namespace Database\Factories;

use App\Enums\TipoCliente;
use App\Models\Cliente;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Cliente>
 */
class ClienteFactory extends Factory
{
    protected $model = Cliente::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'tipo' => TipoCliente::Particular,
            'nombre' => fake()->name(),
            'razon_social' => null,
            'nif' => null,
            'direccion' => fake()->streetAddress(),
            'cp' => fake()->postcode(),
            'ciudad' => fake()->city(),
            'provincia' => fake()->state(),
            'pais' => 'ES',
            'email' => fake()->unique()->safeEmail(),
            'telefono' => fake()->phoneNumber(),
            'aplica_recargo_equivalencia' => false,
            'notas' => null,
        ];
    }

    public function empresa(): static
    {
        return $this->state(fn (array $attributes) => [
            'tipo' => TipoCliente::Empresa,
            'razon_social' => fake()->company(),
            'nif' => 'B12345674',
        ]);
    }

    public function particular(): static
    {
        return $this->state(fn (array $attributes) => [
            'tipo' => TipoCliente::Particular,
            'razon_social' => null,
            'nif' => null,
        ]);
    }
}
