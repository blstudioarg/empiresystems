<?php

namespace App\Support;

use App\Models\Configuracion;
use Illuminate\Support\Facades\Crypt;

/**
 * Config del asistente IA del tenant (feature 030, decisión D7 / patrón EmailTenant).
 *
 * La API key de OpenAI vive cifrada en `configuraciones` (grupo `ia`, clave `ia.api_key`).
 * Solo el backend accede al valor descifrado; a la vista solo se le entrega la versión
 * enmascarada. Nunca leer la fila directamente desde controladores/vistas.
 */
class IaTenant
{
    public const CLAVE_API_KEY = 'ia.api_key';

    public const GRUPO = 'ia';

    public static function configurada(?int $tenantId = null): bool
    {
        return self::apiKey($tenantId) !== '';
    }

    /**
     * API key en claro. Exclusivamente backend (AsistenteIa, prueba de conexión).
     */
    public static function apiKey(?int $tenantId = null): string
    {
        $cifrada = self::fila($tenantId)?->valor;

        if (! $cifrada) {
            return '';
        }

        try {
            return Crypt::decryptString($cifrada);
        } catch (\Throwable) {
            return '';
        }
    }

    /**
     * Versión para la vista: `sk-…XXXX` (últimos 4). Cadena vacía si no hay clave.
     */
    public static function apiKeyEnmascarada(?int $tenantId = null): string
    {
        $clave = self::apiKey($tenantId);

        if ($clave === '') {
            return '';
        }

        $ultimos = substr($clave, -4);

        return 'sk-…'.$ultimos;
    }

    /**
     * Guarda la clave cifrada o, si es null/vacía, borra la fila del grupo `ia`.
     */
    public static function guardarApiKey(?string $apiKey, ?int $tenantId = null): void
    {
        $tenantId ??= tenant()->getTenantKey();

        if ($apiKey === null || trim($apiKey) === '') {
            Configuracion::query()
                ->where('tenant_id', $tenantId)
                ->where('clave', self::CLAVE_API_KEY)
                ->delete();

            return;
        }

        Configuracion::query()->updateOrCreate(
            ['tenant_id' => $tenantId, 'clave' => self::CLAVE_API_KEY],
            ['valor' => Crypt::encryptString(trim($apiKey)), 'tipo' => 'string', 'grupo' => self::GRUPO],
        );
    }

    private static function fila(?int $tenantId): ?Configuracion
    {
        $query = Configuracion::query()->where('clave', self::CLAVE_API_KEY);

        if ($tenantId !== null) {
            $query->where('tenant_id', $tenantId);
        }

        return $query->first();
    }
}
