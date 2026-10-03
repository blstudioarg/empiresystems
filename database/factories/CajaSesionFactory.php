<?php

namespace Database\Factories;

use App\Models\CajaSesion;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * El `tenant_id` sale del tenant activo (y el usuario que abre, también): un factory que creara su
 * propio tenant dejaría tenants huérfanos al sembrar datos de demo (memoria del proyecto).
 *
 * @extends Factory<CajaSesion>
 */
class CajaSesionFactory extends Factory
{
    protected $model = CajaSesion::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'tenant_id' => fn () => tenant()?->getTenantKey() ?? Tenant::factory(),
            'estado' => CajaSesion::ESTADO_ABIERTA,
            'abierta_marca' => 1,
            'fondo_inicial' => 0,
            'abierta_por' => fn (array $atributos) => User::factory()->create(['tenant_id' => $atributos['tenant_id']])->id,
            'abierta_at' => now(),
        ];
    }

    public function conFondo(float|string $fondo): static
    {
        return $this->state(fn () => ['fondo_inicial' => $fondo]);
    }
}
