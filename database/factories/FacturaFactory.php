<?php

namespace Database\Factories;

use App\Enums\EstadoFactura;
use App\Enums\FormaPago;
use App\Enums\RegimenImpositivo;
use App\Enums\TipoFactura;
use App\Models\Cliente;
use App\Models\Factura;
use App\Models\Serie;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Factura>
 */
class FacturaFactory extends Factory
{
    protected $model = Factura::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $tenant = Tenant::factory();

        return [
            'tenant_id' => $tenant,
            'serie_id' => Serie::factory()->for($tenant, 'tenant'),
            'numero' => null,
            'numero_completo' => null,
            'tipo' => TipoFactura::Ordinaria,
            'estado' => EstadoFactura::Borrador,
            'cliente_id' => Cliente::factory()->for($tenant, 'tenant'),
            'fecha_expedicion' => now()->toDateString(),
            'fecha_operacion' => null,
            'fecha_vencimiento' => now()->addDays(30)->toDateString(),
            'forma_pago' => FormaPago::Transferencia,
            'moneda' => 'EUR',
            'regimen_impositivo' => RegimenImpositivo::Iva,
            'aplica_recargo' => false,
            'base_total' => 100,
            'cuota_impuesto_total' => 21,
            'cuota_recargo_total' => 0,
            'irpf_porcentaje' => null,
            'irpf_cuota' => 0,
            'total' => 121,
            'notas' => null,
        ];
    }

    public function emitida(): static
    {
        return $this->state(fn (array $attributes) => [
            'estado' => EstadoFactura::Emitida,
            'numero' => fake()->unique()->numberBetween(1, 100000),
            'numero_completo' => 'F-'.now()->year.'-'.fake()->unique()->numerify('####'),
        ]);
    }

    public function rectificativa(): static
    {
        return $this->state(fn (array $attributes) => [
            'tipo' => TipoFactura::Rectificativa,
            'es_rectificativa' => true,
        ]);
    }
}
