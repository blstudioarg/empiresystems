<?php

namespace App\Services;

use App\Exceptions\CajaYaAbiertaException;
use App\Models\CajaSesion;
use App\Models\User;
use App\Support\DenominacionesEuro;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Abre la caja del tenant activo (feature 048, US1).
 *
 * Como mucho una sesión abierta por tenant (FR-002), con dos barreras: la comprobación previa
 * bloqueante dentro de la transacción y, por debajo, el índice único `(tenant_id, abierta_marca)`,
 * que hace imposible el estado inválido aunque dos tablets lleguen exactamente a la vez (D2).
 */
class AperturaCaja
{
    /**
     * @param  array<int|string, mixed>|null  $conteo  céntimos → cantidad; si llega, manda sobre $fondo
     */
    public function abrir(User $usuario, ?string $fondo, ?array $conteo = null): CajaSesion
    {
        $tenantId = (int) tenant()->getTenantKey();

        $conteoNormalizado = $conteo !== null ? DenominacionesEuro::normalizar($conteo) : null;
        $fondoInicial = $conteoNormalizado !== null
            ? DenominacionesEuro::totalDesde($conteoNormalizado)
            : DenominacionesEuro::aDecimal(DenominacionesEuro::aCentimos($fondo ?? '0'));

        try {
            return DB::transaction(function () use ($tenantId, $usuario, $fondoInicial, $conteoNormalizado) {
                $existente = CajaSesion::query()
                    ->where('tenant_id', $tenantId)
                    ->abierta()
                    ->lockForUpdate()
                    ->first();

                if ($existente) {
                    throw new CajaYaAbiertaException($existente);
                }

                return CajaSesion::create([
                    'tenant_id' => $tenantId,
                    'estado' => CajaSesion::ESTADO_ABIERTA,
                    'abierta_marca' => 1,
                    'fondo_inicial' => $fondoInicial,
                    'conteo_apertura' => $conteoNormalizado ?: null,
                    'abierta_por' => $usuario->id,
                    'abierta_at' => now(),
                ]);
            });
        } catch (QueryException $e) {
            // Violación del índice único: otra apertura ganó la carrera entre la comprobación y el
            // INSERT. Para el usuario es exactamente lo mismo que encontrarla abierta.
            if ((string) $e->getCode() === '23000') {
                throw new CajaYaAbiertaException(
                    CajaSesion::query()->where('tenant_id', $tenantId)->abierta()->first()
                );
            }

            throw $e;
        }
    }

    /** La sesión abierta del tenant activo, o null. */
    public static function sesionAbierta(): ?CajaSesion
    {
        return CajaSesion::query()
            ->where('tenant_id', (int) tenant()->getTenantKey())
            ->abierta()
            ->with('abiertaPor:id,name')
            ->first();
    }
}
