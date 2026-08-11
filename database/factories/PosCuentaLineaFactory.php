<?php

namespace Database\Factories;

use App\Models\PosCuenta;
use App\Models\PosCuentaLinea;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PosCuentaLinea> */
class PosCuentaLineaFactory extends Factory
{
    protected $model = PosCuentaLinea::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'tenant_id' => fn () => tenant()?->getTenantKey() ?? Tenant::factory(),
            'cuenta_id' => PosCuenta::factory(),
            'articulo_id' => null,
            'concepto' => fake()->words(2, true),
            'unidad' => 'ud',
            'cantidad' => 1,
            'precio_unitario' => 10,
            'suplemento_opciones' => 0,
            'tipo_impositivo' => 10,
            'cantidad_saldada' => 0,
            'orden' => 0,
        ];
    }
}
