<?php

namespace Database\Factories;

use App\Models\PosCuenta;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PosCuenta> */
class PosCuentaFactory extends Factory
{
    protected $model = PosCuenta::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'tenant_id' => fn () => tenant()?->getTenantKey() ?? Tenant::factory(),
            'mesa_id' => null,
            'comensales' => null,
            'estado' => PosCuenta::ESTADO_ABIERTA,
            'abierta_en' => now(),
            'version' => 1,
        ];
    }

    public function cerrada(): static
    {
        return $this->state(fn () => ['estado' => PosCuenta::ESTADO_CERRADA, 'cerrada_en' => now()]);
    }

    public function anulada(): static
    {
        return $this->state(fn () => ['estado' => PosCuenta::ESTADO_ANULADA, 'cerrada_en' => now()]);
    }
}
