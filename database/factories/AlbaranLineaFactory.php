<?php

namespace Database\Factories;

use App\Models\Albaran;
use App\Models\AlbaranLinea;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AlbaranLinea>
 */
class AlbaranLineaFactory extends Factory
{
    protected $model = AlbaranLinea::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'albaran_id' => Albaran::factory(),
            'presupuesto_linea_id' => null,
            'articulo_id' => null,
            'concepto' => fake()->words(3, true),
            'unidad' => 'ud',
            'cantidad' => 1,
            'precio_unitario' => 100,
            'descuento_porcentaje' => null,
            'base' => 100,
            'tipo_impositivo' => 21,
            'cuota_impuesto' => 21,
            'tipo_recargo' => null,
            'cuota_recargo' => 0,
            'orden' => 0,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (AlbaranLinea $linea) {
            if ($linea->albaran_id && ! $linea->tenant_id) {
                $linea->tenant_id = Albaran::withoutGlobalScopes()->find($linea->albaran_id)?->tenant_id;
            }
        });
    }
}
