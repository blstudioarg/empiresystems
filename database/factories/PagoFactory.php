<?php

namespace Database\Factories;

use App\Enums\FormaPago;
use App\Models\Factura;
use App\Models\Pago;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Pago>
 */
class PagoFactory extends Factory
{
    protected $model = Pago::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $tenant = Tenant::factory();

        return [
            'tenant_id' => $tenant,
            'factura_id' => Factura::factory()->emitida()->for($tenant, 'tenant'),
            'fecha' => now()->toDateString(),
            'importe' => fake()->randomFloat(2, 10, 100),
            'metodo' => fake()->randomElement(FormaPago::cases()),
            'referencia' => null,
            'anulado_at' => null,
        ];
    }

    public function anulado(): static
    {
        return $this->state(fn (array $attributes) => [
            'anulado_at' => now(),
        ]);
    }
}
