<?php

namespace Database\Factories;

use App\Models\Factura;
use App\Models\FacturaLinea;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FacturaLinea>
 */
class FacturaLineaFactory extends Factory
{
    protected $model = FacturaLinea::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'factura_id' => Factura::factory(),
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
            'calificacion_operacion' => 'S1',
            'causa_exencion' => null,
            'mencion_legal' => null,
            'orden' => 0,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (FacturaLinea $linea) {
            if ($linea->factura_id && ! $linea->tenant_id) {
                $linea->tenant_id = Factura::withoutGlobalScopes()->find($linea->factura_id)?->tenant_id;
            }
        });
    }
}
