<?php

namespace App\Support;

use App\Models\Configuracion;

class RetencionMiembroTenant
{
    public const CLAVE = 'fichajes.retencion_casa_dias';

    /**
     * Default de retención de los datos de casa del miembro tras su baja (RGPD — minimización,
     * D12). Configurable por tenant vía `configuraciones`.
     */
    public const DEFAULT_DIAS = 30;

    public static function dias(int $tenantId): int
    {
        $valor = Configuracion::query()
            ->where('tenant_id', $tenantId)
            ->where('clave', self::CLAVE)
            ->value('valor');

        return $valor !== null ? (int) $valor : self::DEFAULT_DIAS;
    }
}
