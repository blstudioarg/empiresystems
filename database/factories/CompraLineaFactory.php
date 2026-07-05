<?php

namespace Database\Factories;

use App\Models\Compra;
use App\Models\CompraLinea;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CompraLinea>
 */
class CompraLineaFactory extends Factory
{
    protected $model = CompraLinea::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'compra_id' => Compra::factory(),
            'articulo_id' => null,
            'concepto' => fake()->words(3, true),
            'unidad' => null,
            'cantidad' => 1,
            'precio_unitario' => 10,
            'base' => 10,
            'tipo_impositivo' => 21,
            'cuota_impuesto' => 2.1,
            'orden' => 0,
        ];
    }
}
