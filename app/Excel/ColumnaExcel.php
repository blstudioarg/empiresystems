<?php

namespace App\Excel;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Una columna de un módulo, en sus tres usos a la vez: exportar, plantilla e importar
 * (data-model.md §1). `etiqueta` es lo único que se escribe y se espera leer — por eso
 * exportación, plantilla e importación no pueden desincronizarse (FR-024, SC-007): las tres leen
 * esta misma lista, no se mantienen sincronizadas a mano.
 */
class ColumnaExcel
{
    /**
     * @param  string  $clave  Identificador interno: la clave del array de datos ya normalizados
     *                         (la que esperan `validador()`/`crear()`). **No** es necesariamente
     *                         la cabecera normalizada del fichero — esa es siempre
     *                         `normalizarCabecera($etiqueta)` (p. ej. la etiqueta "Recargo de
     *                         equivalencia" normaliza a `recargo_de_equivalencia`, mientras que la
     *                         clave interna es `aplica_recargo_equivalencia`, el nombre real del
     *                         campo). `ImportadorExcel` lee del fichero por la cabecera
     *                         normalizada y reindexa por esta clave antes de validar.
     * @param  string  $etiqueta  Cabecera legible en español escrita en el fichero.
     * @param  bool  $obligatoria  Si debe existir en el fichero importado (solo módulos importables).
     * @param  FormatoCelda  $formato  Formato de celda al exportar.
     * @param  (callable(Model): mixed)  $exportar  Extrae el valor crudo del modelo.
     * @param  (callable(mixed): mixed)|null  $importar  Normaliza el valor leído del fichero. `null` en columnas solo exportables.
     * @param  string|null  $ejemplo  Valor de muestra para la fila de ejemplo de la plantilla (FR-023).
     */
    public function __construct(
        public readonly string $clave,
        public readonly string $etiqueta,
        public readonly bool $obligatoria,
        public readonly FormatoCelda $formato,
        public $exportar,
        public $importar = null,
        public readonly ?string $ejemplo = null,
    ) {}

    /**
     * Normaliza una cabecera para compararla al importar: minúsculas, sin acentos, espacios a
     * guion bajo (data-model.md §1). Es exactamente el mismo algoritmo que usa por defecto
     * Maatwebsite\Excel para las claves de `WithHeadingRow` (`Str::slug($valor, '_')`), así que
     * las cabeceras del fichero llegan ya normalizadas a este formato sin transformación adicional.
     */
    public static function normalizarCabecera(string $etiqueta): string
    {
        return Str::slug($etiqueta, '_');
    }
}
