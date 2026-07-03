<?php

namespace App\Support;

use App\Models\Configuracion;
use Illuminate\Support\Facades\Cache;

class AparienciaTenant
{
    private const CLAVE_PRIMARIO = 'apariencia.color_primario';

    private const CLAVE_SECUNDARIO = 'apariencia.color_secundario';

    private const CLAVE_TOPBAR = 'apariencia.color_topbar';

    private const DEFAULT_PRIMARIO = '#1D69D6';

    private const DEFAULT_SECUNDARIO = '#1F2025';

    private const DEFAULT_TOPBAR = '#FFFFFF';

    /**
     * @return array{color_primario: string|null, color_secundario: string|null, color_topbar: string|null}
     */
    public static function valoresConfigurados(int $tenantId): array
    {
        return Cache::remember(
            self::cacheKey($tenantId),
            now()->addHour(),
            function () use ($tenantId): array {
                $configuraciones = Configuracion::query()
                    ->where('tenant_id', $tenantId)
                    ->where('grupo', 'apariencia')
                    ->pluck('valor', 'clave');

                return [
                    'color_primario' => $configuraciones->get(self::CLAVE_PRIMARIO),
                    'color_secundario' => $configuraciones->get(self::CLAVE_SECUNDARIO),
                    'color_topbar' => $configuraciones->get(self::CLAVE_TOPBAR),
                ];
            }
        );
    }

    /**
     * Colores efectivos: valor configurado por el tenant o default del template.
     *
     * @return array{color_primario: string, color_secundario: string, color_topbar: string}
     */
    public static function coloresEfectivos(int $tenantId): array
    {
        $valores = self::valoresConfigurados($tenantId);

        return [
            'color_primario' => $valores['color_primario'] ?? self::DEFAULT_PRIMARIO,
            'color_secundario' => $valores['color_secundario'] ?? self::DEFAULT_SECUNDARIO,
            'color_topbar' => $valores['color_topbar'] ?? self::DEFAULT_TOPBAR,
        ];
    }

    /**
     * Bloque de variables CSS a inyectar en <head>, solo para las claves configuradas por el
     * tenant (si el tenant no configuró nada, no se emite override y se mantiene el default).
     */
    public static function variablesCss(int $tenantId): string
    {
        $valores = self::valoresConfigurados($tenantId);

        $declaraciones = [];

        if ($valores['color_primario']) {
            $declaraciones[] = "--primary: {$valores['color_primario']};";
            $declaraciones[] = '--primary-hover: '.self::oscurecer($valores['color_primario']).';';

            foreach (range(1, 9) as $decima) {
                $alpha = $decima / 10;
                $declaraciones[] = "--rgba-primary-{$decima}: ".self::hexARgba($valores['color_primario'], $alpha).';';
            }
        }

        if ($valores['color_secundario']) {
            $declaraciones[] = "--secondary: {$valores['color_secundario']};";
        }

        if ($valores['color_topbar']) {
            $declaraciones[] = "--topbar-bg: {$valores['color_topbar']};";
        }

        if ($declaraciones === []) {
            return '';
        }

        return ':root{'.implode('', $declaraciones).'}';
    }

    public static function invalidarCache(int $tenantId): void
    {
        Cache::forget(self::cacheKey($tenantId));
    }

    private static function cacheKey(int $tenantId): string
    {
        return "apariencia-tenant.{$tenantId}";
    }

    private static function hexARgba(string $hex, float $alpha): string
    {
        [$r, $g, $b] = self::hexARgb($hex);

        return "rgba({$r}, {$g}, {$b}, {$alpha})";
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    private static function hexARgb(string $hex): array
    {
        $hex = ltrim($hex, '#');

        return [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        ];
    }

    private static function oscurecer(string $hex, float $factor = 0.85): string
    {
        [$r, $g, $b] = self::hexARgb($hex);

        $r = (int) round($r * $factor);
        $g = (int) round($g * $factor);
        $b = (int) round($b * $factor);

        return sprintf('#%02X%02X%02X', $r, $g, $b);
    }
}
