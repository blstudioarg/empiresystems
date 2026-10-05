<?php

namespace App\Traduccion;

use App\Enums\TipoFactura;
use App\Models\Factura;
use App\Models\PosPrecuenta;
use App\Support\ConfigPos;
use Illuminate\Support\Facades\File;

/**
 * Documentos bilingües y fuente CJK de los PDF del POS (feature 050, research D9).
 *
 * Con el POS del tenant en chino, el ticket (80 mm y A4 simplificada) y la precuenta imprimen cada
 * texto propio como «Total / 合计»: el castellano está siempre en el documento (RD 1619/2012 art.
 * 12.2, docs/02-facturacion-espana.md §3.3). El idioma sale del **tenant del documento**
 * ({@see documentoDe()}), no del locale del request: el PDF se puede generar desde cualquier ruta.
 *
 * dompdf no sustituye glifos entre fuentes, así que un documento con algún carácter chino (en los
 * datos o por ser bilingüe) se pinta entero con Noto Sans SC, con subsetting. Sin ninguno, sigue en
 * DejaVu Sans y se ve exactamente igual que antes (FR-019).
 */
class Bilingue
{
    private const SEPARADOR = ' / ';

    /** Rango CJK: ideogramas (incl. extensión A y compatibilidad) y puntuación/anchos completos. */
    private const PATRON_CJK = '/[\x{3000}-\x{303F}\x{3400}-\x{4DBF}\x{4E00}-\x{9FFF}\x{F900}-\x{FAFF}\x{FF00}-\x{FFEF}]/u';

    /** Idioma de los documentos que se están pintando (null = solo español). */
    private static ?string $idioma = null;

    /**
     * Fija el tenant del documento que se va a pintar. Lo llama cada plantilla al principio; con
     * `null` el documento sale solo en español (p. ej. una factura ordinaria en A4).
     */
    public static function documentoDe(?int $tenantId): void
    {
        self::$idioma = $tenantId !== null ? self::idiomaDe($tenantId) : null;
    }

    /** Idioma traducido del POS del tenant, o null si está en español. */
    public static function idiomaDe(int $tenantId): ?string
    {
        $idioma = ConfigPos::idioma($tenantId);

        return CargadorTraducciones::esIdiomaTraducido($idioma) ? $idioma : null;
    }

    /**
     * `texto` en español; con el POS en otro idioma, `texto / traducción` (o solo `texto` si aún no
     * hay traducción guardada). Las variables se sustituyen en ambos.
     *
     * @param  array<string, mixed>  $params
     */
    public static function texto(string $texto, array $params = []): string
    {
        $espanol = __($texto, $params, config('traduccion.idioma_origen'));

        if (self::$idioma === null) {
            return $espanol;
        }

        $traduccion = __($texto, $params, self::$idioma);

        return $traduccion === $espanol ? $espanol : $espanol.self::SEPARADOR.$traduccion;
    }

    public static function contieneCjk(?string ...$textos): bool
    {
        foreach ($textos as $texto) {
            if ($texto !== null && preg_match(self::PATRON_CJK, $texto)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Ticket (80 mm o A4): fuente CJK si sale bilingüe (solo las simplificadas) o si algún dato
     * del documento tiene caracteres chinos.
     */
    public static function fuenteCjkFactura(Factura $factura): bool
    {
        if ($factura->tipo === TipoFactura::Simplificada && self::idiomaDe((int) $factura->tenant_id) !== null) {
            return true;
        }

        $tenant = $factura->tenant;

        return self::contieneCjk(
            ...$factura->lineas->pluck('concepto')->all(),
            ...[
                $factura->cliente_nombre, $factura->cliente_razon_social, $factura->cliente_direccion,
                $factura->notas, $tenant?->nombre_comercial, $tenant?->razon_social, $tenant?->direccion,
            ],
        );
    }

    public static function fuenteCjkPrecuenta(PosPrecuenta $precuenta): bool
    {
        if (self::idiomaDe((int) $precuenta->tenant_id) !== null) {
            return true;
        }

        $textos = [$precuenta->mesa_nombre, $precuenta->zona_nombre, $precuenta->usuario?->name, $precuenta->tenant?->nombre_comercial];
        foreach ((array) $precuenta->lineas as $linea) {
            $textos[] = $linea['concepto'] ?? null;
            array_push($textos, ...array_map('strval', $linea['opciones'] ?? []));
        }

        return self::contieneCjk(...$textos);
    }

    /**
     * Bloque `<style>` que pasa el documento entero a Noto Sans SC (OFL, `resources/fonts`). Las
     * plantillas lo imprimen pegado a su propio `</style>` y solo con `$fuenteCjk`: un documento
     * sin caracteres chinos sale byte a byte como antes. El controlador activa además el
     * subsetting, para no incrustar la fuente entera (~10 MB) en cada ticket.
     */
    public static function estiloFuenteCjk(): string
    {
        $regular = self::rutaFuente('NotoSansSC-Regular.ttf');
        $negrita = self::rutaFuente('NotoSansSC-Bold.ttf');

        return '<style id="fuente-cjk">'
            ."@font-face { font-family: 'Noto Sans SC'; font-style: normal; font-weight: normal; src: url('{$regular}') format('truetype'); } "
            ."@font-face { font-family: 'Noto Sans SC'; font-style: normal; font-weight: bold; src: url('{$negrita}') format('truetype'); } "
            ."body { font-family: 'Noto Sans SC', sans-serif; }"
            .'</style>';
    }

    /** Ruta de un archivo de la fuente para el `@font-face` de dompdf (barras normales). */
    public static function rutaFuente(string $archivo): string
    {
        return strtr(resource_path('fonts/'.$archivo), [DIRECTORY_SEPARATOR => '/']);
    }

    /**
     * dompdf guarda las métricas de las fuentes en `storage/fonts`; si la carpeta no existe, falla
     * al cargar una fuente nueva.
     */
    public static function prepararCarpetaFuentes(): void
    {
        File::ensureDirectoryExists(storage_path('fonts'));
    }
}
