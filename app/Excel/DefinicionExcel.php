<?php

namespace App\Excel;

use App\Enums\EntidadLogActividad;

/**
 * Contrato base: lo que toda definición de módulo tiene, exportable o no (data-model.md §2.1).
 * La capacidad de exportar o importar se declara aparte, en {@see DefinicionExportable} y
 * {@see DefinicionImportable} — un módulo solo las implementa si le corresponden (D7).
 */
interface DefinicionExcel
{
    /**
     * Clave del módulo en las rutas: clientes, articulos, proveedores, facturas, albaranes, leads.
     */
    public function modulo(): string;

    /**
     * Nombre legible para el nombre de fichero y los mensajes.
     */
    public function etiquetaModulo(): string;

    /**
     * Permiso requerido: ver-clientes, ver-articulos, … (FR-004, FR-020).
     */
    public function permiso(): string;

    /**
     * Columnas, en orden de aparición en el fichero.
     *
     * @return ColumnaExcel[]
     */
    public function columnas(): array;

    /**
     * Para el registro de actividad (FR-008, FR-021).
     */
    public function entidadLog(): EntidadLogActividad;
}
