<?php

namespace App\Traduccion;

use App\Models\Traduccion;
use App\Models\TraduccionCorreccion;
use Illuminate\Contracts\Translation\Loader;
use Throwable;

/**
 * Cargador de traducciones de Laravel con las traducciones en base de datos (feature 050,
 * research D2).
 *
 * Para el grupo JSON (`*`/`*`) de un idioma traducido (distinto del español de origen) combina, en
 * este orden de prioridad creciente: los archivos de `lang/` (si los hubiera), la tabla central
 * `traducciones` (solo las `traducida`) y las correcciones del tenant activo (FR-018). En español
 * —o en cualquier idioma no configurado— no consulta nada: `__()` devuelve la propia clave, que es
 * el texto en español (SC-002).
 *
 * Se llama una vez por idioma y request: el `Translator` cachea lo cargado (SC-003).
 */
class CargadorTraducciones implements Loader
{
    public function __construct(private readonly Loader $original) {}

    public static function esIdiomaTraducido(?string $locale): bool
    {
        return $locale !== null
            && $locale !== config('traduccion.idioma_origen')
            && array_key_exists($locale, (array) config('traduccion.idiomas'));
    }

    public function load($locale, $group, $namespace = null)
    {
        $lineas = $this->original->load($locale, $group, $namespace);

        if ($group !== '*' || $namespace !== '*' || ! self::esIdiomaTraducido($locale)) {
            return $lineas;
        }

        // Si la base no está disponible (deploy a medias, tabla sin migrar) el POS se ve en
        // español en vez de romperse (FR-013).
        try {
            return array_merge($lineas, $this->desdeBaseDeDatos($locale));
        } catch (Throwable $e) {
            report($e);

            return $lineas;
        }
    }

    /** @return array<string, string> */
    private function desdeBaseDeDatos(string $locale): array
    {
        $lineas = Traduccion::query()
            ->traducidas()
            ->where('idioma', $locale)
            ->pluck('traduccion', 'texto')
            ->all();

        // Sin tenant activo no hay correcciones que aplicar (y el scope de tenancy no filtraría).
        if (function_exists('tenant') && tenant() !== null) {
            $lineas = array_merge($lineas, TraduccionCorreccion::query()
                ->where('idioma', $locale)
                ->pluck('traduccion', 'texto')
                ->all());
        }

        return $lineas;
    }

    public function addNamespace($namespace, $hint)
    {
        $this->original->addNamespace($namespace, $hint);
    }

    public function addJsonPath($path)
    {
        $this->original->addJsonPath($path);
    }

    public function namespaces()
    {
        return $this->original->namespaces();
    }
}
