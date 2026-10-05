<?php

namespace App\Traduccion;

use App\Exceptions\TraduccionNoDisponibleException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Proveedor DeepL vía el cliente HTTP de Laravel, sin SDK (feature 050, research D6–D8).
 *
 * - Variables `:nombre` → `<x>:nombre</x>` y siglas no traducibles → `<k>…</k>`, ambas con
 *   `ignore_tags`: DeepL no las traduce y, al tener contenido, las coloca bien en la frase (con
 *   etiquetas vacías las dejaba en cualquier sitio). Se restauran al volver.
 * - Un texto entero en MAYÚSCULAS («ARQUEO») se envía en minúsculas: DeepL deja sin traducir lo
 *   que parece una sigla, y el chino no tiene mayúsculas que conservar.
 * - Textos con HTML (guías de ayuda) con `tag_handling=html`.
 * - Glosario remoto llamado `empire-pos-es-<idioma>-<hash8 de las entradas>`: su nombre identifica
 *   la versión, así que no hace falta guardar estado local (D7). El id se cachea para el respaldo
 *   de primer uso; si la caché se vacía se busca por nombre.
 */
class TraductorDeepl implements ProveedorTraduccion
{
    private const CACHE_GLOSARIO = 'traduccion.glosario.';

    public function traducir(array $textos, string $idioma, bool $html, ?int $timeout = null): array
    {
        if ($textos === []) {
            return [];
        }

        $preparados = array_map(fn (string $texto) => $this->proteger($texto, $html), $textos);

        $cuerpo = [
            'text' => $preparados,
            'source_lang' => 'ES',
            'target_lang' => config("traduccion.deepl.idiomas_destino.{$idioma}", strtoupper($idioma)),
            'tag_handling' => $html ? 'html' : 'xml',
        ];

        if (! $html) {
            $cuerpo['ignore_tags'] = ['x', 'k'];
        }

        if ($glosario = $this->glosarioVigente($idioma, $timeout)) {
            $cuerpo['glossary_id'] = $glosario;
        }

        $respuesta = $this->enviar(fn (PendingRequest $http) => $http->post('/v2/translate', $cuerpo), $timeout);

        $traducciones = $respuesta->json('translations');

        if (! is_array($traducciones) || count($traducciones) !== count($textos)) {
            throw new TraduccionNoDisponibleException('Respuesta de DeepL inesperada.');
        }

        return array_map(fn (array $t) => $this->restaurar((string) ($t['text'] ?? ''), $html), $traducciones);
    }

    public function sincronizarGlosario(string $idioma, array $entradas): ?string
    {
        $entradas = self::conVariantes($entradas);

        if ($entradas === []) {
            return null;
        }

        $nombre = self::nombreGlosario($idioma, $entradas);
        $prefijo = config('traduccion.glosario_prefijo').$idioma.'-';
        $existentes = $this->glosarios();

        $vigente = collect($existentes)->firstWhere('name', $nombre)['glossary_id'] ?? null;

        // Los glosarios de versiones anteriores sobran. Se borran ANTES de crear el nuevo: el plan
        // gratuito de DeepL rechaza (456) crear un glosario más mientras exista el anterior.
        foreach ($existentes as $glosario) {
            if (str_starts_with((string) $glosario['name'], $prefijo) && $glosario['glossary_id'] !== $vigente) {
                Cache::forget(self::CACHE_GLOSARIO.$idioma);
                $this->enviar(fn (PendingRequest $http) => $http->delete('/v2/glossaries/'.$glosario['glossary_id']));
            }
        }

        if ($vigente === null) {
            $tsv = collect($entradas)->map(fn (string $destino, string $origen) => "{$origen}\t{$destino}")->implode("\n");

            $vigente = $this->enviar(fn (PendingRequest $http) => $http->post('/v2/glossaries', [
                'name' => $nombre,
                'source_lang' => 'es',
                'target_lang' => $idioma,
                'entries' => $tsv,
                'entries_format' => 'tsv',
            ]))->json('glossary_id');
        }

        if ($vigente) {
            Cache::forever(self::CACHE_GLOSARIO.$idioma, $vigente);
        }

        return $vigente;
    }

    /**
     * Nombre del glosario remoto para unas entradas: cambia en cuanto cambia cualquier entrada.
     *
     * @param  array<string, string>  $entradas
     */
    public static function nombreGlosario(string $idioma, array $entradas): string
    {
        ksort($entradas);

        return config('traduccion.glosario_prefijo').$idioma.'-'.substr(hash('sha256', json_encode($entradas, JSON_UNESCAPED_UNICODE)), 0, 8);
    }

    /**
     * Añade la variante con minúscula inicial de cada término («Mesa» → «mesa»), para que el
     * glosario se aplique también dentro de una frase. Las frases fijadas con variables
     * («Hace :min min») no van al glosario remoto: allí nunca coincidirían (se envían con las
     * variables protegidas); se aplican localmente, como texto exacto.
     *
     * @param  array<string, string>  $entradas
     * @return array<string, string>
     */
    public static function conVariantes(array $entradas): array
    {
        $resultado = [];

        foreach ($entradas as $origen => $destino) {
            $origen = trim((string) $origen);

            if ($origen === '' || trim((string) $destino) === '' || str_contains($origen, ':')) {
                continue;
            }

            $resultado[$origen] = (string) $destino;
            $minuscula = mb_strtolower(mb_substr($origen, 0, 1)).mb_substr($origen, 1);
            $resultado[$minuscula] ??= (string) $destino;
        }

        return $resultado;
    }

    /**
     * Texto entero en mayúsculas (sin contar las variables) y sin siglas protegidas: «ARQUEO»,
     * «TOTAL FACTURADO». Con una sigla dentro («IVA») no se toca, para no perderla.
     */
    private static function esMayusculas(string $texto): bool
    {
        $sinVariables = preg_replace('/:[A-Za-z_][A-Za-z0-9_]*/', '', $texto);

        if (! preg_match('/\p{L}{2,}/u', $sinVariables) || $sinVariables !== mb_strtoupper($sinVariables)) {
            return false;
        }

        foreach ((array) config('traduccion.no_traducir', []) as $sigla) {
            if (str_contains($texto, $sigla)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Id del glosario de la versión actual. Para el respaldo de primer uso: no crea nada, solo lo
     * busca (caché o, si se vació, por nombre).
     */
    private function glosarioVigente(string $idioma, ?int $timeout): ?string
    {
        $entradas = self::conVariantes((array) config("traduccion.glosario.{$idioma}", []));

        if ($entradas === []) {
            return null;
        }

        if ($id = Cache::get(self::CACHE_GLOSARIO.$idioma)) {
            return $id;
        }

        $nombre = self::nombreGlosario($idioma, $entradas);
        $id = collect($this->glosarios($timeout))->firstWhere('name', $nombre)['glossary_id'] ?? null;

        if ($id) {
            Cache::forever(self::CACHE_GLOSARIO.$idioma, $id);
        }

        return $id;
    }

    /** @return list<array{glossary_id: string, name: string}> */
    private function glosarios(?int $timeout = null): array
    {
        return (array) $this->enviar(fn (PendingRequest $http) => $http->get('/v2/glossaries'), $timeout)->json('glossaries', []);
    }

    /** @param  callable(PendingRequest): Response  $llamada */
    private function enviar(callable $llamada, ?int $timeout = null): Response
    {
        $clave = (string) config('traduccion.deepl.api_key');

        if ($clave === '') {
            throw new TraduccionNoDisponibleException('Falta DEEPL_API_KEY.');
        }

        $http = Http::baseUrl(rtrim((string) config('traduccion.deepl.api_url'), '/'))
            ->withHeaders(['Authorization' => 'DeepL-Auth-Key '.$clave])
            ->acceptJson()
            ->timeout($timeout ?? (int) config('traduccion.timeout_comando', 30));

        try {
            $respuesta = $llamada($http);
        } catch (ConnectionException $e) {
            throw new TraduccionNoDisponibleException('DeepL no responde: '.$e->getMessage(), 0, $e);
        }

        if ($respuesta->failed()) {
            $motivo = match ($respuesta->status()) {
                456 => 'cupo de caracteres agotado',
                403 => 'clave no válida',
                429 => 'demasiadas peticiones',
                default => 'error del servicio',
            };

            throw new TraduccionNoDisponibleException("DeepL HTTP {$respuesta->status()}: {$motivo}.");
        }

        return $respuesta;
    }

    private function proteger(string $texto, bool $html): string
    {
        if (! $html && self::esMayusculas($texto)) {
            $texto = mb_strtoupper(mb_substr(mb_strtolower($texto), 0, 1)).mb_substr(mb_strtolower($texto), 1);
        }

        if (! $html) {
            $texto = htmlspecialchars($texto, ENT_NOQUOTES | ENT_XML1, 'UTF-8');
        }

        $texto = preg_replace('/:([A-Za-z_][A-Za-z0-9_]*)/', $html ? '<x translate="no">:$1</x>' : '<x>:$1</x>', $texto);

        foreach ((array) config('traduccion.no_traducir', []) as $sigla) {
            $patron = '/(?<![\p{L}\p{N}])'.preg_quote(htmlspecialchars($sigla, ENT_NOQUOTES | ENT_XML1), '/').'(?![\p{L}\p{N}])/u';
            $texto = preg_replace($patron, $html ? '<k translate="no">$0</k>' : '<k>$0</k>', $texto);
        }

        return $texto;
    }

    private function restaurar(string $texto, bool $html): string
    {
        $texto = preg_replace('#<x(?:\s[^>]*)?>(.*?)</x>#su', '$1', $texto);
        $texto = preg_replace('#<k(?:\s[^>]*)?>(.*?)</k>#su', '$1', $texto);

        return $html ? $texto : htmlspecialchars_decode($texto, ENT_NOQUOTES | ENT_XML1);
    }
}
