<?php

namespace App\Support;

use App\Models\Configuracion;

/**
 * Plazo de conservación de las conversaciones del asistente (feature 045).
 *
 * Calcado de `RetencionLogsTenant`: la constitución (Principio II, Additional Constraints) obliga a
 * reutilizar ese patrón —clave por tenant en `configuraciones` + comando de purga programado— en vez
 * de inventar un mecanismo de retención por feature.
 *
 * Contexto: hasta esta feature la conversación era efímera y moría con la sesión, así que no había
 * nada que retener. Al persistirla pasa a ser dato personal conservado y necesita plazo y purga
 * desde el primer diseño.
 */
class RetencionAsistenteTenant
{
    public const CLAVE_RETENCION_DIAS = 'asistente.retencion_dias';

    public const GRUPO = IaTenant::GRUPO;

    /**
     * 90 días sin actividad. No hay referencia normativa que fije este número (a diferencia de los
     * 2 años del registro de accesos): es un plazo elegido por producto, suficientemente largo para
     * que el historial sea útil y suficientemente corto para no acumular conversaciones olvidadas.
     */
    public const DEFAULT_RETENCION_DIAS = 90;

    public static function dias(int $tenantId): int
    {
        $valor = Configuracion::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('clave', self::CLAVE_RETENCION_DIAS)
            ->value('valor');

        return $valor !== null ? (int) $valor : self::DEFAULT_RETENCION_DIAS;
    }
}
