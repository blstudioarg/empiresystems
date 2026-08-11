<?php

namespace Database\Factories;

use App\Models\PosZona;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PosZona>
 *
 * `tenant_id` toma el **tenant activo** cuando lo hay, y solo cae en `Tenant::factory()` si no
 * existe contexto (tests unitarios que crean su propio tenant). El default ciego a
 * `Tenant::factory()` que usan las factories antiguas hace que un seeder de demo genere tenants
 * fantasma sin que nadie se entere.
 */
class PosZonaFactory extends Factory
{
    protected $model = PosZona::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'tenant_id' => fn () => tenant()?->getTenantKey() ?? Tenant::factory(),
            'nombre' => fake()->unique()->words(2, true),
            'suplemento_porcentaje' => 0,
            'orden' => 0,
        ];
    }

    public function conSuplemento(float $porcentaje): static
    {
        return $this->state(fn () => ['suplemento_porcentaje' => $porcentaje]);
    }
}
