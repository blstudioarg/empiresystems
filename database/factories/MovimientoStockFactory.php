<?php

namespace Database\Factories;

use App\Enums\OrigenMovimientoStock;
use App\Enums\TipoMovimientoStock;
use App\Models\Articulo;
use App\Models\MovimientoStock;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MovimientoStock>
 */
class MovimientoStockFactory extends Factory
{
    protected $model = MovimientoStock::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'articulo_id' => Articulo::factory(),
            'tipo' => TipoMovimientoStock::Ajuste,
            'cantidad' => 1,
            'stock_resultante' => 1,
            'origen' => OrigenMovimientoStock::AjusteManual,
            'motivo' => fake()->sentence(),
            'ocurrido_at' => now(),
        ];
    }
}
