<?php

namespace App\Services;

use App\Excel\Informes\InformeComercialExport;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Genera el `.xlsx` multi-hoja del informe comercial (research D7, feature 033). No pasa por
 * `DefinicionExportable` (feature 031): ese contrato es "N filas de un modelo por IDs" y este
 * informe es un agregado multi-bloque sin modelo de respaldo.
 */
class ExportadorInformeComercial
{
    /**
     * @param  array<string, mixed>  $datos  resultado de `InformeComercial::generar()`
     */
    public function descargar(array $datos): BinaryFileResponse
    {
        $nombreFichero = sprintf('informe-comercial-%s.xlsx', now()->format('Y-m-d'));

        return Excel::download(new InformeComercialExport($datos), $nombreFichero);
    }
}
