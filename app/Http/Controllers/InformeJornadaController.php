<?php

namespace App\Http\Controllers;

use App\Models\MiembroEquipo;
use App\Services\InformeJornada;
use App\Support\Cumplimiento\ServicioCumplimiento;
use App\Support\RangoFechas;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class InformeJornadaController extends Controller
{
    public function __construct(
        private readonly InformeJornada $informeJornada,
        private readonly ServicioCumplimiento $servicioCumplimiento,
    ) {}

    public function index(Request $request): View|JsonResponse
    {
        $tenantId = tenant()->getTenantKey();

        $miembros = MiembroEquipo::where('tenant_id', $tenantId)->with('user')->orderBy('id')->get();

        $miembro = $request->filled('miembro_id')
            ? MiembroEquipo::where('tenant_id', $tenantId)->find($request->integer('miembro_id'))
            : null;

        $rango = RangoFechas::desdePeticion($request->all());

        $datos = $miembro
            ? $this->informeJornada->generar($miembro, $rango->desde, $rango->hasta->copy()->endOfDay())
            : null;

        $cumplimiento = $miembro
            ? $this->servicioCumplimiento->evaluarRango($miembro, $rango)
            : null;

        if ($request->wantsJson()) {
            return response()->json([
                'html' => view('partials.jornada-resultado', [
                    'miembroSeleccionado' => $miembro,
                    'rango' => $rango,
                    'datos' => $datos,
                    'cumplimiento' => $cumplimiento,
                ])->render(),
            ]);
        }

        return view('fichajes.informe', [
            'miembros' => $miembros,
            'miembroSeleccionado' => $miembro,
            'rango' => $rango,
            'datos' => $datos,
            'cumplimiento' => $cumplimiento,
        ]);
    }

    public function exportar(Request $request): Response
    {
        $tenantId = tenant()->getTenantKey();

        $miembro = MiembroEquipo::where('tenant_id', $tenantId)->findOrFail($request->integer('miembro_id'));
        $rango = RangoFechas::desdePeticion($request->all());
        $datos = $this->informeJornada->generar($miembro, $rango->desde, $rango->hasta->copy()->endOfDay());
        $cumplimiento = $this->servicioCumplimiento->evaluarRango($miembro, $rango);

        $pdf = Pdf::loadView('fichajes.informe-pdf', [
            'miembro' => $miembro,
            'rango' => $rango,
            'datos' => $datos,
            'cumplimiento' => $cumplimiento,
        ]);

        return $pdf->stream("jornada-{$miembro->id}.pdf");
    }
}
