<?php

namespace App\Support;

use App\Models\Configuracion;

class RetencionGeoTenant
{
    public const CLAVE = 'fichajes.retencion_geo_dias';

    /**
     * Default de retención del dato de geo del fichaje (RGPD — minimización, D5). Configurable
     * por tenant vía `configuraciones`. La fila de jornada NO se purga con este plazo (4 años,
     * FR-022): solo se nulifican las columnas de geo.
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
