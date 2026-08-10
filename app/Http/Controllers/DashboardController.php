<?php

namespace App\Http\Controllers;

use App\Http\Requests\DashboardFiltroRequest;
use App\Services\DashboardEstadisticas;
use App\Support\RangoFechas;
use App\Support\ResolvedorLanding;

class DashboardController extends Controller
{
    private const AVISO_RANGO_INVALIDO = 'El rango de fechas indicado no es válido. Mostrando el mes en curso.';

    public function index(DashboardFiltroRequest $request, DashboardEstadisticas $dashboardEstadisticas, ResolvedorLanding $resolvedorLanding)
    {
        // El Super Admin ya no llega aquí: BloquearSuperAdminAreaTenant (037-super-admin-panel-aislado)
        // lo intercepta antes, en el grupo de rutas, y lo manda a super_admin.home.

        // Landing sin permiso de dashboard (feature 027 D11/RN-07 + doc 09 Cambio 5): la ruta `/` no
        // lleva `can:` para no dar un 403 de bienvenida; los usuarios sin `ver-dashboard` aterrizan
        // en la primera sección para la que tienen permiso (Perfil como fallback universal).
        if (! $request->user()->can('ver-dashboard')) {
            return redirect($resolvedorLanding->urlPara($request->user()));
        }

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
