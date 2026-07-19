<?php

namespace App\Excel;

use Illuminate\Database\Eloquent\Builder;

/**
 * Interfaz de capacidad: solo la implementan los módulos con exportación (data-model.md §2.2).
 * La implementan clientes, artículos, facturas, albaranes y leads. No la implementa proveedores
 * (fuera del alcance de exportación, ver §3.3).
 */
interface DefinicionExportable extends DefinicionExcel
{
    /**
     * Consulta base para exportar, con sus `with()` de relaciones. Siempre sobre el modelo
     * Eloquent, para que el TenantScope se aplique (Principio I) — nunca `withoutGlobalScopes()`
     * ni una consulta cruda (ver contracts/exportacion.md).
     *
     * @param  int[]  $ids
     */
    public function consultaExportacion(array $ids): Builder;
}
