<?php

namespace App\Services;

use App\Exceptions\CajaCerradaException;
use App\Exceptions\CajaYaCerradaException;
use App\Exceptions\ObservacionRequeridaException;
use App\Models\CajaSesion;
use App\Models\User;
use App\Support\ConfigPos;
use App\Support\DenominacionesEuro;
use Illuminate\Support\Facades\DB;

/**
 * Cierra la caja con arqueo (feature 048, US2).
 *
 * Todo ocurre en una transacción con la sesión abierta bloqueada, la misma que lee
 * `RegistroTicket` al emitir: o el ticket entra antes y cuenta en este cierre, o espera y cae en la
 * sesión siguiente. Nunca "entre medias" (FR-004, research D2).
 *
 * El esperado, el contado (desde el mapa de denominaciones si llega) y la diferencia se calculan
 * aquí, nunca en el cliente (Principio III). Al cerrar se **congelan** en la propia sesión, junto
 * con el desglose completo: el informe Z de ese día ya no depende de nada que pase después
 * (FR-017, research D4).
 */
class CierreCaja
{
    public function __construct(private readonly ResumenCaja $resumen) {}

    /**
     * @param  array<int|string, mixed>|null  $conteo  céntimos → cantidad
     */
    public function cerrar(User $usuario, int $sesionId, ?array $conteo, ?string $contado, ?string $observacion): CajaSesion
    {
        $tenantId = (int) tenant()->getTenantKey();

        return DB::transaction(function () use ($tenantId, $usuario, $sesionId, $conteo, $contado, $observacion) {
            $sesion = CajaSesion::query()
                ->where('tenant_id', $tenantId)
                ->abierta()
                ->lockForUpdate()
                ->first();

            // La sesión que el usuario contaba ya está cerrada (otra tablet se adelantó): se le
            // enseña ese cierre en vez de cerrar por accidente una sesión nueva que no contó.
            if ($sesion === null || $sesion->id !== $sesionId) {
                $yaCerrada = CajaSesion::query()
                    ->where('tenant_id', $tenantId)
                    ->cerrada()
                    ->with('cerradaPor:id,name')
                    ->find($sesionId);

                if ($yaCerrada) {
                    throw new CajaYaCerradaException($yaCerrada);
                }

                throw CajaCerradaException::sinSesion();
            }

            $conteoNormalizado = $conteo !== null ? DenominacionesEuro::normalizar($conteo) : null;
            $contadoCent = $conteoNormalizado !== null
                ? DenominacionesEuro::aCentimos(DenominacionesEuro::totalDesde($conteoNormalizado))
                : DenominacionesEuro::aCentimos($contado ?? '0');

            $calculo = $this->resumen->calcular($sesion);
            $esperadoCent = DenominacionesEuro::aCentimos($calculo['efectivo_esperado']);
            $descuadreCent = $contadoCent - $esperadoCent;

            $resultado = [
                'efectivo_esperado' => DenominacionesEuro::aDecimal($esperadoCent),
                'efectivo_contado' => DenominacionesEuro::aDecimal($contadoCent),
                'descuadre' => DenominacionesEuro::aDecimal($descuadreCent),
                'estado' => CajaSesion::estadoDeDescuadre(DenominacionesEuro::aDecimal($descuadreCent)),
            ];

            $umbralCent = DenominacionesEuro::aCentimos(ConfigPos::cajaUmbralDescuadre($tenantId));
            if (abs($descuadreCent) > $umbralCent && ($observacion === null || trim($observacion) === '')) {
                throw new ObservacionRequeridaException($resultado);
            }

            $sesion->fill([
                'estado' => CajaSesion::ESTADO_CERRADA,
                'abierta_marca' => null,
                'cerrada_por' => $usuario->id,
                'cerrada_at' => now(),
                'num_tickets' => $calculo['num_tickets'],
                'total_facturado' => $calculo['total_facturado'],
                'efectivo_ventas' => $calculo['efectivo_ventas'],
                'entradas' => $calculo['entradas'],
                'salidas' => $calculo['salidas'],
                'efectivo_esperado' => $resultado['efectivo_esperado'],
                'efectivo_contado' => $resultado['efectivo_contado'],
                'conteo_cierre' => $conteoNormalizado ?: null,
                'descuadre' => $resultado['descuadre'],
                'observacion' => $observacion !== null ? trim($observacion) : null,
                'resumen' => $calculo['resumen'],
            ])->save();

            return $sesion->setRelation('cerradaPor', $usuario);
        });
    }
}
