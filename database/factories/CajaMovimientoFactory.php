<?php

namespace Database\Factories;

use App\Models\CajaMovimiento;
use App\Models\CajaSesion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CajaMovimiento> */
class CajaMovimientoFactory extends Factory
{
    protected $model = CajaMovimiento::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'caja_sesion_id' => CajaSesion::factory(),
            'tenant_id' => fn (array $a) => CajaSesion::withoutGlobalScopes()->find($a['caja_sesion_id'])?->tenant_id,
            'tipo' => CajaMovimiento::TIPO_SALIDA,
            'importe' => 10,
            'motivo' => 'Pago a proveedor',
            'usuario_id' => fn (array $a) => User::factory()->create(['tenant_id' => $a['tenant_id']])->id,
        ];
    }
}
