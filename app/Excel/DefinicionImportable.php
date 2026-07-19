<?php

namespace App\Excel;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Model;

/**
 * Interfaz de capacidad: solo la implementan los módulos con importación (data-model.md §2.3).
 * La implementan clientes, artículos y proveedores. No la implementan facturas, albaranes ni
 * leads — en esas definiciones `validador()`/`crear()` no existen, lo que hace que la
 * prohibición de importarlas (FR-010) sea cierta por construcción y no una comprobación en
 * runtime que alguien pueda olvidar (§2.4).
 */
interface DefinicionImportable extends DefinicionExcel
{
    /**
     * Validador de una fila ya normalizada. Delega en el FormRequest del alta manual (D3):
     * mismas `rules()`, mismos `messages()`/`attributes()` en español, para que sean *las
     * mismas* validaciones y no un juego paralelo más laxo.
     *
     * @param  array<string, mixed>  $fila
     */
    public function validador(array $fila): Validator;

    /**
     * Persiste una fila ya validada, forzando `tenant_id` (FR-015): el tenant nunca viene del
     * fichero, aunque el fichero traiga una columna que pretenda fijarlo.
     *
     * @param  array<string, mixed>  $datos
     */
    public function crear(array $datos, int $tenantId): Model;

    /**
     * Claves de `columnas()` que deben ser únicas *dentro del propio fichero*, además de contra
     * la base de datos (edge case "duplicados dentro del propio archivo", research.md D3). Por
     * ejemplo `['nif']` en clientes/proveedores. Vacío si el módulo no tiene ningún campo que
     * deba ser único (p. ej. artículos, cuyo SKU no es un campo único en el alta manual).
     *
     * @return string[]
     */
    public function camposUnicos(): array;
}
