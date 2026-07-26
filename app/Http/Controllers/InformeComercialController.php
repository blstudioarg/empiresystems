<?php

namespace App\Http\Controllers;

use App\Enums\AccionLogActividad;
use App\Enums\EntidadLogActividad;
use App\Http\Requests\InformeComercialFiltroRequest;
use App\Models\CanalCaptacion;
use App\Models\User;
use App\Services\ExportadorInformeComercial;
use App\Services\InformeComercial;
use App\Services\RegistradorActividad;
use App\Support\AlcanceInformeComercial;
use App\Support\FiltrosInforme;
use App\Support\RangoFechas;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class InformeComercialController extends Controller
{
    private const AVISO_RANGO_INVALIDO = 'El rango de fechas indicado no es válido. Mostrando el mes en curso.';

    public function index(InformeComercialFiltroRequest $request, InformeComercial $informeComercial): View|JsonResponse
    {
        $alcance = AlcanceInformeComercial::paraUsuario($request->user());

        abort_unless($alcance->tieneAccesoAlgunBloque(), 403);

        $rangoInvalido = $request->huboRangoInvalido();
        $rango = $rangoInvalido ? RangoFechas::mesEnCurso() : RangoFechas::desdePeticion($request->validated());

        $filtros = $alcance->resolverFiltros(FiltrosInforme::desdePeticion($request->validated()));

        $datos = $informeComercial->generar($rango, $filtros, $alcance);

        if ($request->wantsJson()) {
            return response()->json([
                'html' => view('partials.informe-comercial-contenido', ['datos' => $datos])->render(),
                'periodo' => $datos['periodo'],
                'alcance' => $datos['alcance'],
                'graficos' => [
                    'evolucion' => $datos['evolucion'],
                    'leads_por_estado' => $datos['fases']['leads_por_estado'],
                    'oportunidades_por_etapa' => $datos['fases']['oportunidades_por_etapa'],
                    'presupuestos_por_estado' => $datos['fases']['presupuestos_por_estado'],
                    'comparativa' => $datos['comparativa'],
                ],
                'aviso' => $rangoInvalido ? self::AVISO_RANGO_INVALIDO : null,
            ]);
        }

        if ($rangoInvalido) {
            session()->flash('warning', self::AVISO_RANGO_INVALIDO);
        }

        return view('informes-comerciales.index', [
            'datos' => $datos,
            'comerciales' => $alcance->esTenant()
                ? User::where('tenant_id', tenant()->id)->orderBy('name')->get(['id', 'name'])
                : collect(),
            'canales' => CanalCaptacion::orderBy('orden')->orderBy('nombre')->get(['id', 'nombre']),
        ]);
    }

    /**
     * Descarga del informe en `.xlsx` (FR-027). Resuelve los filtros de nuevo en servidor: no se
     * confía en nada precalculado por el cliente (FR-028).
     */
    public function exportar(
        InformeComercialFiltroRequest $request,
        InformeComercial $informeComercial,
        ExportadorInformeComercial $exportador,
        RegistradorActividad $registradorActividad,
    ): BinaryFileResponse {
        $alcance = AlcanceInformeComercial::paraUsuario($request->user());

        abort_unless($alcance->tieneAccesoAlgunBloque(), 403);

        $rango = $request->huboRangoInvalido() ? RangoFechas::mesEnCurso() : RangoFechas::desdePeticion($request->validated());
        $filtros = $alcance->resolverFiltros(FiltrosInforme::desdePeticion($request->validated()));

        $datos = $informeComercial->generar($rango, $filtros, $alcance);

        $registradorActividad->registrar(
            $request->user(),
            AccionLogActividad::Exportacion,
            EntidadLogActividad::InformeComercial,
            null,
            'Exportó el informe comercial',
        );

        return $exportador->descargar($datos);
    }
}
