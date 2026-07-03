<?php

namespace Database\Factories;

use App\Models\Factura;
use App\Models\FacturaEvento;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FacturaEvento>
 */
class FacturaEventoFactory extends Factory
{
    protected $model = FacturaEvento::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $tenant = Tenant::factory();

        return [
            'tenant_id' => $tenant,
            'factura_id' => Factura::factory()->for($tenant, 'tenant'),
            'tipo_evento' => 'emitida',
            'detalle' => ['numero_completo' => 'F-'.now()->year.'-0001', 'fecha_expedicion' => now()->toDateString()],
            'huella' => null,
            'ocurrido_at' => now(),
        ];
    }
}
