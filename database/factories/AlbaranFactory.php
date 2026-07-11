<?php

namespace Database\Factories;

use App\Enums\EstadoAlbaran;
use App\Enums\RegimenImpositivo;
use App\Models\Albaran;
use App\Models\Cliente;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Albaran>
 */
class AlbaranFactory extends Factory
{
    protected $model = Albaran::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $tenant = Tenant::factory();

        return [
            'tenant_id' => $tenant,
            'numero' => 'A-'.now()->year.'-'.fake()->unique()->numerify('####'),
            'presupuesto_id' => null,
            'cliente_id' => Cliente::factory()->for($tenant, 'tenant'),
            'estado' => EstadoAlbaran::Borrador,
            'receptor_nombre' => fake()->name(),
            'receptor_nif' => null,
            'receptor_direccion' => fake()->streetAddress(),
            'receptor_cp' => fake()->postcode(),
            'receptor_ciudad' => fake()->city(),
            'receptor_provincia' => fake()->state(),
            'receptor_pais' => 'ES',
            'fecha_entrega' => null,
            'regimen_impositivo' => RegimenImpositivo::Iva,
            'aplica_recargo' => false,
            'base_total' => 100,
            'cuota_impuesto_total' => 21,
            'cuota_recargo_total' => 0,
            'total' => 121,
            'convertido_a_factura_id' => null,
            'notas' => null,
        ];
    }

    public function entregado(): static
    {
        return $this->state(fn (array $attributes) => [
            'estado' => EstadoAlbaran::Entregado,
            'fecha_entrega' => now()->toDateString(),
        ]);
    }

    public function facturado(): static
    {
        return $this->state(fn (array $attributes) => [
            'estado' => EstadoAlbaran::Facturado,
        ]);
    }
}
