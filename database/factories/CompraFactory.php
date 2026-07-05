<?php

namespace Database\Factories;

use App\Enums\EstadoCompra;
use App\Models\Compra;
use App\Models\Proveedor;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Compra>
 */
class CompraFactory extends Factory
{
    protected $model = Compra::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'proveedor_id' => Proveedor::factory(),
            'numero_documento' => fake()->bothify('FAC-####'),
            'fecha' => now()->toDateString(),
            'estado' => EstadoCompra::Borrador,
            'base_total' => 0,
            'cuota_impuesto_total' => 0,
            'total' => 0,
            'notas' => null,
        ];
    }

    public function confirmada(): static
    {
        return $this->state(fn (array $attributes) => [
            'estado' => EstadoCompra::Confirmada,
            'confirmada_at' => now(),
        ]);
    }
}
