<?php

namespace App\Services;

use App\Exceptions\CajaCerradaException;
use App\Models\CajaMovimiento;
use App\Models\CajaSesion;
use App\Models\User;
use App\Support\DenominacionesEuro;
use Illuminate\Support\Facades\DB;

/**
 * Entradas y salidas manuales de efectivo (feature 048, US3). Siempre contra la sesión abierta del
 * tenant, leída con bloqueo: un movimiento nunca cae en una caja que se está cerrando en ese
 * instante desde otra tablet.
 */
class MovimientosCaja
{
    public function registrar(User $usuario, string $tipo, string $importe, string $motivo): CajaMovimiento
    {
        $tenantId = (int) tenant()->getTenantKey();

        return DB::transaction(function () use ($tenantId, $usuario, $tipo, $importe, $motivo) {
            $sesion = CajaSesion::query()
                ->where('tenant_id', $tenantId)
                ->abierta()
                ->lockForUpdate()
                ->first();

            if ($sesion === null) {
                throw CajaCerradaException::sinSesion();
            }

            return CajaMovimiento::create([
                'tenant_id' => $tenantId,
                'caja_sesion_id' => $sesion->id,
                'tipo' => $tipo,
                'importe' => DenominacionesEuro::aDecimal(DenominacionesEuro::aCentimos($importe)),
                'motivo' => $motivo,
                'usuario_id' => $usuario->id,
            ])->setRelation('sesion', $sesion);
        });
    }
}
