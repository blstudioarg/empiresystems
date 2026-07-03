<?php

namespace Database\Factories;

use App\Enums\TipoImpuesto;
use App\Models\Factura;
use App\Models\FacturaImpuesto;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FacturaImpuesto>
 */
class FacturaImpuestoFactory extends Factory
{
    protected $model = FacturaImpuesto::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'factura_id' => Factura::factory(),
            'tipo_impuesto' => TipoImpuesto::Iva,
            'porcentaje' => 21,
            'base_imponible' => 100,
            'cuota' => 21,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (FacturaImpuesto $impuesto) {
            if ($impuesto->factura_id && ! $impuesto->tenant_id) {
                $impuesto->tenant_id = Factura::withoutGlobalScopes()->find($impuesto->factura_id)?->tenant_id;
            }
        });
    }
}
