<?php

namespace Tests\Concerns;

use App\Models\Traduccion;
use App\Traduccion\ExtractorClaves;
use Illuminate\Support\Facades\DB;

/**
 * Diccionario falso para los tests de traducción (feature 050): cada clave extraída del ámbito
 * `pos` se traduce como `⟦<texto>⟧`. Así cualquier texto español visible **sin** corchetes delata
 * un texto propio de la app que no está marcado con `__()` / `__t()`. Ningún test llama a DeepL.
 */
trait DiccionarioFalso
{
    protected function cargarDiccionarioFalso(string $idioma = 'zh'): void
    {
        $ahora = now();
        $filas = array_map(fn (string $texto) => [
            'idioma' => $idioma,
            'hash' => Traduccion::hashDe($texto),
            'texto' => $texto,
            'ambito' => 'pos',
            'es_html' => $texto !== strip_tags($texto),
            'traduccion' => self::marcar($texto),
            'estado' => Traduccion::ESTADO_TRADUCIDA,
            'intentos' => 0,
            'traducida_en' => $ahora,
            'vista_en' => $ahora,
            'created_at' => $ahora,
            'updated_at' => $ahora,
        ], app(ExtractorClaves::class)->extraer('pos'));

        foreach (array_chunk($filas, 100) as $lote) {
            DB::table('traducciones')->insertOrIgnore($lote);
        }

        app('translator')->setLoaded([]);
    }

    protected static function marcar(string $texto): string
    {
        return '⟦'.$texto.'⟧';
    }
}
