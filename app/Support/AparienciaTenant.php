<?php

namespace App\Support;

use App\Models\Configuracion;
use Illuminate\Support\Facades\Cache;

class AparienciaTenant
{
    private const CLAVE_PRIMARIO = 'apariencia.color_primario';

    private const CLAVE_SECUNDARIO = 'apariencia.color_secundario';

    private const CLAVE_TOPBAR = 'apariencia.color_topbar';

    private const CLAVE_FACEBOOK = 'apariencia.facebook_url';

    private const CLAVE_INSTAGRAM = 'apariencia.instagram_url';

    private const CLAVE_TITULO_LOGIN = 'apariencia.titulo_login';

    public const DEFAULT_PRIMARIO = '#1D69D6';

    public const DEFAULT_SECUNDARIO = '#1F2025';

    public const DEFAULT_TOPBAR = '#000000';

    public const DEFAULT_FACEBOOK = '';

    public const DEFAULT_INSTAGRAM = '';

    public const DEFAULT_TITULO_LOGIN = 'Iniciar sesión';

    /**
     * Topbar del panel central (super_admin): no pertenece a ningún tenant, así que no hay color
     * de marca que aplicar; un gris neutro lo distingue visualmente del área de negocio de un
     * tenant (que sí usa DEFAULT_TOPBAR/colores configurados).
     */
    public const DEFAULT_TOPBAR_CENTRAL = '#6C757D';

    /**
     * @return array{color_primario: string|null, color_secundario: string|null, color_topbar: string|null,
     *     facebook_url: string|null, instagram_url: string|null, titulo_login: string|null}
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
                    'facebook_url' => $configuraciones->get(self::CLAVE_FACEBOOK),
                    'instagram_url' => $configuraciones->get(self::CLAVE_INSTAGRAM),
                    'titulo_login' => $configuraciones->get(self::CLAVE_TITULO_LOGIN),
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
     * Redes sociales y título de login efectivos: valor configurado por el tenant o default.
     *
     * @return array{facebook_url: string, instagram_url: string, titulo_login: string}
     */
    public static function extrasEfectivos(int $tenantId): array
    {
        $valores = self::valoresConfigurados($tenantId);

        return [
            'facebook_url' => $valores['facebook_url'] ?? self::DEFAULT_FACEBOOK,
            'instagram_url' => $valores['instagram_url'] ?? self::DEFAULT_INSTAGRAM,
            'titulo_login' => $valores['titulo_login'] ?? self::DEFAULT_TITULO_LOGIN,
        ];
    }

    /**
     * Bloque de variables CSS a inyectar en <head>, solo para las claves configuradas por el
     * tenant (si el tenant no configuró nada, no se emite override y se mantiene el default).
     *
     * Se declara en `:root` y también en `body`, ambas con !important: el motor de esquemas de
     * color del template (dzSettings, en vendor/global/global.min.js) fija en cada carga de
     * página un atributo `data-primary="color_N"` / `data-secondary="color_N"` sobre `<body>`, y
     * style.css redefine ahí mismo --primary/--secondary (y sus derivados hover/dark/rgba) al
     * color por defecto del template. Como todo el contenido vive dentro de `<body>`, esa regla
     * queda más cerca que un `:root` (en `<html>`) y gana la herencia aunque nuestra hoja cargue
     * después — un `:root{...}` normal nunca llega a aplicarse. Sin tocar ese motor (sigue
     * gestionando sidebar/layout/dark mode), forzamos aquí el color de marca del tenant por
     * encima de su default con !important.
     */
    public static function variablesCss(int $tenantId): string
    {
        $valores = self::valoresConfigurados($tenantId);

        $declaraciones = [];

        if ($valores['color_primario']) {
            $declaraciones[] = "--primary: {$valores['color_primario']} !important;";
            $declaraciones[] = '--primary-hover: '.self::oscurecer($valores['color_primario']).' !important;';

            foreach (range(1, 9) as $decima) {
                $alpha = $decima / 10;
                $declaraciones[] = "--rgba-primary-{$decima}: ".self::hexARgba($valores['color_primario'], $alpha).' !important;';
            }
        }

        if ($valores['color_secundario']) {
            $declaraciones[] = "--secondary: {$valores['color_secundario']} !important;";
        }

        if ($valores['color_topbar']) {
            $declaraciones[] = "--topbar-bg: {$valores['color_topbar']};";
        }

        if ($declaraciones === []) {
            return '';
        }

        return ':root, body{'.implode('', $declaraciones).'}';
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
