<?php

namespace Database\Factories;

use App\Models\PosCuenta;
use App\Models\PosPrecuenta;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PosPrecuenta> */
class PosPrecuentaFactory extends Factory
{
    protected $model = PosPrecuenta::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'tenant_id' => fn () => tenant()?->getTenantKey() ?? Tenant::factory(),
            'cuenta_id' => PosCuenta::factory(),
            'mesa_id' => null,
            'mesa_nombre' => null,
            'zona_nombre' => null,
            'usuario_id' => null,
            'emitida_en' => now(),
            'cuenta_version' => 1,
            'huella_consumo' => hash('sha256', fake()->uuid()),
            'huella_pendiente' => hash('sha256', fake()->uuid()),
            'reimpresion' => false,
            'regimen_impositivo' => 'iva',
            'suplemento_zona' => 0,
            'comensales' => null,
            'lineas' => [['concepto' => 'Caña', 'opciones' => [], 'cantidad' => 1, 'precio_unitario' => '2.20', 'importe' => '2.20']],
            'total' => 2.20,
        ];
    }
}
