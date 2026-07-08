<?php

namespace App\Support;

use App\Enums\EstrategiaAsignacion;
use App\Models\Configuracion;

/**
 * Configuración por tenant del módulo CRM (leads/presupuestos, feature 028), mismo patrón
 * clave/valor de `configuraciones` que {@see ConfigFichajes}.
 */
class ConfigCrm
{
    public const CLAVE_RETENCION_DIAS = 'leads.retencion_dias';

    public const CLAVE_ASIGNACION_ESTRATEGIA = 'leads.asignacion_estrategia';

    public const CLAVE_ASIGNACION_COMERCIALES = 'leads.asignacion_comerciales';

    public const CLAVE_ASIGNACION_ULTIMO_INDICE = 'leads.asignacion_ultimo_indice';

    public const CLAVE_DIAS_VALIDEZ_PRESUPUESTO = 'presupuesto.dias_validez';

    /**
     * Default de retención de leads descartados/no convertidos (RGPD, research D5): 3 años.
     */
    public const DEFAULT_RETENCION_DIAS = 1095;

    public const DEFAULT_DIAS_VALIDEZ_PRESUPUESTO = 30;

    public static function retencionDias(int $tenantId): int
    {
        $valor = self::valor($tenantId, self::CLAVE_RETENCION_DIAS);

        return $valor !== null ? (int) $valor : self::DEFAULT_RETENCION_DIAS;
    }

    public static function estrategiaAsignacion(int $tenantId): EstrategiaAsignacion
    {
        $valor = self::valor($tenantId, self::CLAVE_ASIGNACION_ESTRATEGIA);

        return $valor !== null ? EstrategiaAsignacion::from($valor) : EstrategiaAsignacion::Manual;
    }

    /**
     * @return list<int>
     */
    public static function comercialesAsignacion(int $tenantId): array
    {
        $valor = self::valor($tenantId, self::CLAVE_ASIGNACION_COMERCIALES);

        if ($valor === null) {
            return [];
        }

        $ids = json_decode($valor, true);

        return is_array($ids) ? array_values(array_map('intval', $ids)) : [];
    }

    public static function diasValidezPresupuesto(int $tenantId): int
    {
        $valor = self::valor($tenantId, self::CLAVE_DIAS_VALIDEZ_PRESUPUESTO);

        return $valor !== null ? (int) $valor : self::DEFAULT_DIAS_VALIDEZ_PRESUPUESTO;
    }

    private static function valor(int $tenantId, string $clave): ?string
    {
        return Configuracion::query()
            ->where('tenant_id', $tenantId)
            ->where('clave', $clave)
            ->value('valor');
    }
}
