<?php

namespace App\Traduccion;

use Illuminate\Support\Facades\File;
use InvalidArgumentException;

/**
 * Extrae las claves traducibles de un ámbito (feature 050, research D5): los textos literales de
 * `__('…')`, `@lang('…')`, `bilingue('…')` (Blade/PHP) y `__t('…')` (JS) en los archivos y carpetas que declara
 * `config('traduccion.ambitos.<ámbito>.rutas')`, más las `claves_extra` que no salen por
 * expresión regular (etiquetas del menú, que vienen del catálogo).
 *
 * Una llamada con una variable (`__($etiqueta)`) no se extrae: esos textos tienen que llegar por
 * `claves_extra` o, en último caso, por el respaldo de primer uso.
 */
class ExtractorClaves
{
    private const PATRON = '/(?<![\w$])(?:__t?|@lang|bilingue)\(\s*([\'"])((?:\\\\.|(?!\1)[^\\\\])*)\1/su';

    /** @return list<string> */
    public function extraer(string $ambito): array
    {
        $config = config("traduccion.ambitos.{$ambito}");

        if (! is_array($config)) {
            throw new InvalidArgumentException("Ámbito de traducción desconocido: {$ambito}");
        }

        $claves = [];

        foreach ($this->archivos((array) ($config['rutas'] ?? [])) as $archivo) {
            foreach ($this->clavesDe((string) file_get_contents($archivo)) as $clave) {
                $claves[$clave] = true;
            }
        }

        foreach ((array) ($config['claves_extra'] ?? []) as $extra) {
            $textos = is_callable($extra) ? (array) call_user_func($extra) : [$extra];

            foreach ($textos as $texto) {
                if (is_string($texto) && trim($texto) !== '') {
                    $claves[$texto] = true;
                }
            }
        }

        return array_keys($claves);
    }

    /** @return list<string> */
    public function clavesDe(string $contenido): array
    {
        preg_match_all(self::PATRON, $contenido, $coincidencias, PREG_SET_ORDER);

        $claves = [];
        foreach ($coincidencias as $c) {
            $texto = self::desescapar($c[2], $c[1]);

            if (trim($texto) !== '') {
                $claves[] = $texto;
            }
        }

        return $claves;
    }

    private static function desescapar(string $texto, string $comilla): string
    {
        return preg_replace_callback('/\\\\(.)/su', function (array $m) use ($comilla) {
            return in_array($m[1], [$comilla, '\\'], true) ? $m[1] : '\\'.$m[1];
        }, $texto);
    }

    /**
     * @param  list<string>  $rutas
     * @return list<string>
     */
    private function archivos(array $rutas): array
    {
        $archivos = [];

        foreach ($rutas as $ruta) {
            $absoluta = base_path($ruta);

            if (is_file($absoluta)) {
                $archivos[] = $absoluta;
            } elseif (is_dir($absoluta)) {
                foreach (File::allFiles($absoluta) as $archivo) {
                    if (in_array($archivo->getExtension(), ['php', 'js'], true)) {
                        $archivos[] = $archivo->getPathname();
                    }
                }
            }
        }

        sort($archivos);

        return array_values(array_unique($archivos));
    }
}
