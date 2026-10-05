<?php

use App\Traduccion\Bilingue;

/*
 * Helpers globales de la traducción (feature 050). Se cargan desde AppServiceProvider::register()
 * y no desde el `files` del autoload de Composer: el despliegue a este hosting es por FTP, sin
 * `composer install`, y el autoload del servidor no se enteraría (ver registrarAssetVersionado).
 */

if (! function_exists('bilingue')) {
    /**
     * Texto de un documento PDF del POS: `texto` en español o `texto / traducción` con el POS en
     * otro idioma (research D9). El tenant lo fija la plantilla con Bilingue::documentoDe().
     *
     * @param  array<string, mixed>  $params
     */
    function bilingue(string $texto, array $params = []): string
    {
        return Bilingue::texto($texto, $params);
    }
}
