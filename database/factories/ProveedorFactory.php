<?php

namespace Database\Factories;

use App\Models\Proveedor;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Proveedor>
 */
class ProveedorFactory extends Factory
{
    protected $model = Proveedor::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'nombre' => fake()->company(),
            'razon_social' => fake()->company().' S.L.',
            'nif' => 'B'.fake()->unique()->numerify('########'),
            'direccion' => fake()->streetAddress(),
            'cp' => fake()->postcode(),
            'ciudad' => fake()->city(),
            'provincia' => fake()->state(),
            'pais' => 'ES',
            'email' => fake()->unique()->companyEmail(),
            'telefono' => fake()->phoneNumber(),
            'notas' => null,
        ];
    }
}
