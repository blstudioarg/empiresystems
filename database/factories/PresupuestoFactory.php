<?php

namespace Database\Factories;

use App\Enums\EstadoPresupuesto;
use App\Enums\RegimenImpositivo;
use App\Models\Cliente;
use App\Models\Presupuesto;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Presupuesto>
 */
class PresupuestoFactory extends Factory
{
    protected $model = Presupuesto::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $tenant = Tenant::factory();

        return [
            'tenant_id' => $tenant,
            'numero' => 'P-'.now()->year.'-'.fake()->unique()->numerify('####'),
            'oportunidad_id' => null,
            'cliente_id' => Cliente::factory()->for($tenant, 'tenant'),
            'lead_id' => null,
            'estado' => EstadoPresupuesto::Borrador,
            'receptor_nombre' => fake()->name(),
            'receptor_nif' => null,
            'receptor_direccion' => fake()->streetAddress(),
            'receptor_cp' => fake()->postcode(),
            'receptor_ciudad' => fake()->city(),
            'receptor_provincia' => fake()->state(),
            'receptor_pais' => 'ES',
            'fecha_emision' => now()->toDateString(),
            'fecha_validez' => now()->addDays(30)->toDateString(),
            'fecha_envio' => null,
            'regimen_impositivo' => RegimenImpositivo::Iva,
            'aplica_recargo' => false,
            'base_total' => 100,
            'cuota_impuesto_total' => 21,
            'cuota_recargo_total' => 0,
            'irpf_porcentaje' => null,
            'irpf_cuota' => 0,
            'total' => 121,
            'convertido_a_factura_id' => null,
            'notas' => null,
        ];
    }

    public function aceptado(): static
    {
        return $this->state(fn (array $attributes) => [
            'estado' => EstadoPresupuesto::Aceptado,
        ]);
    }

    public function facturado(): static
    {
        return $this->state(fn (array $attributes) => [
            'estado' => EstadoPresupuesto::Facturado,
        ]);
    }
}
