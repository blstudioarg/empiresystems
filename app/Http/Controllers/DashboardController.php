<?php

namespace App\Http\Controllers;

use App\Http\Requests\DashboardFiltroRequest;
use App\Services\DashboardEstadisticas;
use App\Support\RangoFechas;

class DashboardController extends Controller
{
    private const AVISO_RANGO_INVALIDO = 'El rango de fechas indicado no es válido. Mostrando el mes en curso.';

    public function index(DashboardFiltroRequest $request, DashboardEstadisticas $dashboardEstadisticas)
    {
        $rangoInvalido = $request->huboRangoInvalido();
        $rango = $rangoInvalido ? RangoFechas::mesEnCurso() : RangoFechas::desdePeticion($request->validated());
        $datos = $dashboardEstadisticas->resumen($rango);

        if ($request->wantsJson()) {
            return response()->json([
                'html' => view('partials.dashboard-contenido', ['datos' => $datos])->render(),
                'rango' => $datos['rango'],
                'graficos' => [
                    'serie_facturacion' => $datos['serie_facturacion'],
                    'comparativo' => $datos['comparativo'],
                    'distribucion_estados' => $datos['distribucion_estados'],
                ],
                'aviso' => $rangoInvalido ? self::AVISO_RANGO_INVALIDO : null,
            ]);
        }

        if ($rangoInvalido) {
            session()->flash('warning', self::AVISO_RANGO_INVALIDO);
        }

        return view('dashboard', [
            'datos' => $datos,
        ]);
    }
}
