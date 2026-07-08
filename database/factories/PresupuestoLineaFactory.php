<?php

namespace Database\Factories;

use App\Models\Presupuesto;
use App\Models\PresupuestoLinea;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PresupuestoLinea>
 */
class PresupuestoLineaFactory extends Factory
{
    protected $model = PresupuestoLinea::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'presupuesto_id' => Presupuesto::factory(),
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
        return $this->afterMaking(function (PresupuestoLinea $linea) {
            if ($linea->presupuesto_id && ! $linea->tenant_id) {
                $linea->tenant_id = Presupuesto::withoutGlobalScopes()->find($linea->presupuesto_id)?->tenant_id;
            }
        });
    }
}
