<?php

namespace App\Http\Controllers;

use App\Enums\AccionLogActividad;
use App\Excel\RegistroDefiniciones;
use App\Http\Requests\ExportarRequest;
use App\Services\ExportadorExcel;
use App\Services\RegistradorActividad;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExportacionController extends Controller
{
    public function __construct(
        private readonly RegistroDefiniciones $registro,
        private readonly ExportadorExcel $exportador,
        private readonly RegistradorActividad $registradorActividad,
    ) {}

    /**
     * POST /exportar/{modulo} (contracts/exportacion.md). Los IDs los propone el cliente (filas
     * visibles del DataTable, research.md D1); el servidor decide cuáles puede ver realmente vía
     * el TenantScope de `consultaExportacion()` — nunca `withoutGlobalScopes()` ni consulta cruda.
     */
    public function exportar(ExportarRequest $request, string $modulo): BinaryFileResponse
    {
        $definicion = $this->registro->resolverExportable($modulo);
        $ids = $request->validated('ids');

        $total = $this->exportador->contarFilas($definicion, $ids);

        $nombreFichero = sprintf('%s-%s.xlsx', $definicion->modulo(), now()->format('Y-m-d'));

        $this->registradorActividad->registrar(
            auth()->user(),
            AccionLogActividad::Exportacion,
            $definicion->entidadLog(),
            null,
            "Exportó {$total} {$definicion->etiquetaModulo()} a Excel",
        );

        return $this->exportador->descargar($definicion, $ids, $nombreFichero);
    }
}
