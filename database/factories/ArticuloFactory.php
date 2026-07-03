<?php

namespace Database\Factories;

use App\Enums\TipoArticulo;
use App\Models\Articulo;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Articulo>
 */
class ArticuloFactory extends Factory
{
    protected $model = Articulo::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'tipo' => TipoArticulo::Producto,
            'sku' => fake()->unique()->bothify('SKU-####'),
            'nombre' => fake()->words(3, true),
            'descripcion' => fake()->sentence(),
            'unidad' => 'ud',
            'precio' => fake()->randomFloat(2, 1, 500),
            'tipo_impositivo' => 21,
            'gestion_stock' => false,
            'stock_actual' => null,
            'stock_minimo' => null,
            'aplica_recargo_equivalencia' => false,
            'activo' => true,
        ];
    }

    public function producto(): static
    {
        return $this->state(fn (array $attributes) => [
            'tipo' => TipoArticulo::Producto,
            'unidad' => 'ud',
        ]);
    }

    public function servicio(): static
    {
        return $this->state(fn (array $attributes) => [
            'tipo' => TipoArticulo::Servicio,
            'unidad' => 'hora',
            'gestion_stock' => false,
            'stock_actual' => null,
            'stock_minimo' => null,
        ]);
    }
}
