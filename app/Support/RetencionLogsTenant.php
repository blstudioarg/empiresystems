<?php

namespace App\Support;

use App\Models\Configuracion;

class RetencionLogsTenant
{
    public const CLAVE_RETENCION_DIAS = 'logs.retencion_dias';

    /**
     * Default de retención del registro de accesos (RGPD — minimización), referencia RD 1720/2007:
     * 2 años. Configurable por tenant vía `configuraciones`.
     */
    public const DEFAULT_RETENCION_DIAS = 730;

    public static function dias(int $tenantId): int
    {
        $valor = Configuracion::query()
            ->where('tenant_id', $tenantId)
            ->where('clave', self::CLAVE_RETENCION_DIAS)
            ->value('valor');

        return $valor !== null ? (int) $valor : self::DEFAULT_RETENCION_DIAS;
    }
}
