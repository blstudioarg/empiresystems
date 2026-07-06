<?php

namespace App\Support;

/**
 * Etiqueta legible (navegador + sistema operativo) a partir del user-agent crudo guardado en
 * `logs_actividad` (registro de accesos RGPD/LOPDGDD). Parseo best-effort por substrings: no
 * pretende precisión forense, solo un dato legible para el administrador del tenant.
 */
class AgenteUsuario
{
    public static function label(?string $userAgent): ?string
    {
        if (! $userAgent) {
            return null;
        }

        return trim(self::navegador($userAgent).' en '.self::sistemaOperativo($userAgent));
    }

    private static function navegador(string $ua): string
    {
        return match (true) {
            str_contains($ua, 'Edg/') => 'Edge',
            str_contains($ua, 'OPR/') || str_contains($ua, 'Opera') => 'Opera',
            str_contains($ua, 'Chrome') && ! str_contains($ua, 'Chromium') => 'Chrome',
            str_contains($ua, 'Firefox') => 'Firefox',
            str_contains($ua, 'Safari') && ! str_contains($ua, 'Chrome') => 'Safari',
            default => 'Navegador desconocido',
        };
    }

    private static function sistemaOperativo(string $ua): string
    {
        return match (true) {
            str_contains($ua, 'Windows') => 'Windows',
            str_contains($ua, 'Mac OS X') || str_contains($ua, 'Macintosh') => 'macOS',
            str_contains($ua, 'Android') => 'Android',
            str_contains($ua, 'iPhone') || str_contains($ua, 'iPad') => 'iOS',
            str_contains($ua, 'Linux') => 'Linux',
            default => 'sistema desconocido',
        };
    }
}
