<?php

namespace App\Http\Controllers;

use App\Enums\AccionLogActividad;
use App\Enums\EntidadLogActividad;
use App\Http\Requests\ImportarLeadsRequest;
use App\Services\ImportadorLeads;
use App\Services\RegistradorActividad;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class LeadImportacionController extends Controller
{
    public function __construct(
        private readonly ImportadorLeads $importador,
        private readonly RegistradorActividad $registradorActividad,
    ) {}

    public function form(): View
    {
        return view('leads.importar');
    }

    public function importar(ImportarLeadsRequest $request): RedirectResponse
    {
        $resultado = $this->importador->importar($request->file('fichero'), tenant()->id);

        $this->registradorActividad->registrar(
            auth()->user(),
            AccionLogActividad::Alta,
            EntidadLogActividad::Lead,
            null,
            "Importó leads: {$resultado->importados} creados, ".count($resultado->rechazadas).' rechazados',
        );

        return redirect()->route('leads.importar.form')->with([
            'resumen_importacion' => [
                'importados' => $resultado->importados,
                'rechazadas' => $resultado->rechazadas,
            ],
        ]);
    }
}
