<?php

namespace App\Services;

use App\Excel\DefinicionExcel;
use App\Excel\DefinicionExportable;
use App\Excel\ExportacionModulo;
use App\Excel\PlantillaModulo;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExportadorExcel
{
    /**
     * Cuenta las filas que realmente se van a escribir (tras el TenantScope), para el registro de
     * actividad (FR-008) — la diferencia entre este número y el de `ids` recibidos es la señal de
     * un intento de acceso cruzado entre tenants.
     *
     * @param  int[]  $ids
     */
    public function contarFilas(DefinicionExportable $definicion, array $ids): int
    {
        return $definicion->consultaExportacion($ids)->count();
    }

    /**
     * @param  int[]  $ids
     */
    public function descargar(DefinicionExportable $definicion, array $ids, string $nombreFichero): BinaryFileResponse
    {
        return Excel::download(new ExportacionModulo($definicion, $ids), $nombreFichero);
    }

    public function plantilla(DefinicionExcel $definicion, string $nombreFichero): BinaryFileResponse
    {
        return Excel::download(new PlantillaModulo($definicion), $nombreFichero);
    }
}
