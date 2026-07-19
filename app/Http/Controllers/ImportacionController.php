<?php

namespace App\Http\Controllers;

use App\Enums\AccionLogActividad;
use App\Excel\RechazosExport;
use App\Excel\RegistroDefiniciones;
use App\Exceptions\ImportacionInvalidaException;
use App\Http\Requests\ImportarExcelRequest;
use App\Services\ExportadorExcel;
use App\Services\ImportadorExcel;
use App\Services\RegistradorActividad;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Form, plantilla, previsualización y confirmación de importación (contracts/importacion.md).
 * Solo se registran rutas para clientes/articulos/proveedores — facturas/albaranes/leads no
 * implementan `DefinicionImportable`, así que ni siquiera existe una ruta que resolver aquí
 * (FR-010, ver RegistroDefiniciones).
 */
class ImportacionController extends Controller
{
    public function __construct(
        private readonly RegistroDefiniciones $registro,
        private readonly ImportadorExcel $importador,
        private readonly ExportadorExcel $exportador,
        private readonly RegistradorActividad $registradorActividad,
    ) {}

    public function form(string $modulo): View
    {
        $definicion = $this->registro->resolverImportable($modulo);

        return view('excel.importar', [
            'definicion' => $definicion,
            'modulo' => $modulo,
            'previsualizacion' => null,
        ]);
    }

    public function plantilla(string $modulo): BinaryFileResponse
    {
        $definicion = $this->registro->resolverImportable($modulo);

        return $this->exportador->plantilla($definicion, "plantilla-{$definicion->modulo()}.xlsx");
    }

    public function previsualizar(ImportarExcelRequest $request, string $modulo): View|JsonResponse
    {
        $definicion = $this->registro->resolverImportable($modulo);

        try {
            $previsualizacion = $this->importador->previsualizar($definicion, $request->file('fichero'));
        } catch (ImportacionInvalidaException $e) {
            if ($request->wantsJson()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return back()->withErrors(['fichero' => $e->getMessage()])->withInput();
        }

        if ($request->wantsJson()) {
            return response()->json($previsualizacion->toArray());
        }

        return view('excel.importar', [
            'definicion' => $definicion,
            'modulo' => $modulo,
            'previsualizacion' => $previsualizacion,
        ]);
    }

    public function confirmar(Request $request, string $modulo): RedirectResponse|JsonResponse
    {
        $definicion = $this->registro->resolverImportable($modulo);

        $datos = $request->validate([
            'token' => ['required', 'string', 'uuid'],
        ]);

        try {
            $resultado = $this->importador->confirmar($definicion, $datos['token'], (int) tenant()->getTenantKey());
        } catch (ImportacionInvalidaException $e) {
            if ($request->wantsJson()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return back()->withErrors(['token' => $e->getMessage()]);
        }

        $rechazadas = array_map(fn ($f) => $f->toArray(), $resultado->rechazadas);

        // Persistente (no flash): el enlace de descarga de rechazos vive en la misma página que
        // el resumen, así que debe sobrevivir a la petición GET que la vuelve a pedir.
        session()->put("rechazos_importacion.{$datos['token']}", $rechazadas);

        $this->registradorActividad->registrar(
            auth()->user(),
            AccionLogActividad::Alta,
            $definicion->entidadLog(),
            null,
            "Importó {$definicion->etiquetaModulo()}: {$resultado->importados} creados, ".count($rechazadas).' rechazados',
        );

        if ($request->wantsJson()) {
            return response()->json([
                'importados' => $resultado->importados,
                'rechazadas' => $rechazadas,
                'token' => $datos['token'],
                'rechazos_url' => count($rechazadas) > 0
                    ? route("{$modulo}.importar.rechazos", ['modulo' => $modulo, 'token' => $datos['token']])
                    : null,
            ]);
        }

        return redirect()->route("{$modulo}.importar.form")->with('resumen_importacion', [
            'importados' => $resultado->importados,
            'rechazadas' => $rechazadas,
            'token' => $datos['token'],
        ]);
    }

    public function rechazos(string $modulo, string $token): BinaryFileResponse
    {
        $this->registro->resolverImportable($modulo);

        $rechazadas = session()->get("rechazos_importacion.{$token}", []);

        return Excel::download(new RechazosExport($rechazadas), "rechazos-{$modulo}.xlsx");
    }
}
