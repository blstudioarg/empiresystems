<?php

namespace App\Support;

use App\Models\Configuracion;

/**
 * Flags de configuración del módulo de fichajes por tenant (D8/D9), reutilizando el patrón
 * clave/valor de `configuraciones` ya usado por el resto del proyecto.
 *
 * La zona horaria ya NO vive aquí: se promovió a {@see \App\Support\ConfigTenant} porque es una
 * config global del tenant (aplica a todo el front), no específica de fichajes.
 */
class ConfigFichajes
{
    public const CLAVE_GEOFENCING_BLOQUEANTE = 'fichajes.geofencing_bloqueante';

    public const CLAVE_REGISTRAR_PAUSAS = 'fichajes.registrar_pausas';

    public const CLAVE_TOLERANCIA_RETRASO_MIN = 'fichajes.tolerancia_retraso_min';

    public const CLAVE_TOLERANCIA_EXCESO_MIN = 'fichajes.tolerancia_exceso_min';

    public const DEFAULT_TOLERANCIA_RETRASO_MIN = 5;

    public const DEFAULT_TOLERANCIA_EXCESO_MIN = 15;

    public static function geofencingBloqueante(int $tenantId): bool
    {
        return self::flag($tenantId, self::CLAVE_GEOFENCING_BLOQUEANTE);
    }

    public static function registrarPausas(int $tenantId): bool
    {
        return self::flag($tenantId, self::CLAVE_REGISTRAR_PAUSAS);
    }

    public static function toleranciaRetrasoMin(int $tenantId): int
    {
        return self::entero($tenantId, self::CLAVE_TOLERANCIA_RETRASO_MIN, self::DEFAULT_TOLERANCIA_RETRASO_MIN);
    }

    public static function toleranciaExcesoMin(int $tenantId): int
    {
        return self::entero($tenantId, self::CLAVE_TOLERANCIA_EXCESO_MIN, self::DEFAULT_TOLERANCIA_EXCESO_MIN);
    }

    private static function flag(int $tenantId, string $clave): bool
    {
        $valor = Configuracion::query()
            ->where('tenant_id', $tenantId)
            ->where('clave', $clave)
            ->value('valor');

        return $valor !== null ? (bool) (int) $valor : false;
    }

    private static function entero(int $tenantId, string $clave, int $default): int
    {
        $valor = Configuracion::query()
            ->where('tenant_id', $tenantId)
            ->where('clave', $clave)
            ->value('valor');

        return $valor !== null ? (int) $valor : $default;
    }
}
