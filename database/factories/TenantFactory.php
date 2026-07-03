<?php

namespace Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Stancl\Tenancy\Database\Models\Domain;

/**
 * @extends Factory<Tenant>
 */
class TenantFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre_comercial' => fake()->unique()->company(),
            'razon_social' => fake()->company().' S.L.',
            'nif' => fake()->unique()->numerify('B########'),
            'regimen_impositivo' => 'iva',
            'email' => fake()->unique()->companyEmail(),
            'activo' => true,
        ];
    }

    /**
     * Resolución de tenant por dominio (007-super-admin-tenants): todo tenant de test necesita
     * un `domains` propio para que las peticiones puedan resolverlo por host. Dominio de test
     * único y determinístico (no depende de fake(), que no es único de forma fiable aquí).
     */
    public function configure(): static
    {
        return $this->afterCreating(function (Tenant $tenant) {
            if ($tenant->domains()->exists()) {
                return;
            }

            Domain::create([
                'domain' => "tenant{$tenant->id}.test",
                'tenant_id' => $tenant->id,
            ]);
        });
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'activo' => false,
        ]);
    }
}
