<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Models\CajaSesion;
use App\Support\DenominacionesEuro;
use App\Traduccion\Bilingue;
use App\Traduccion\CargadorTraducciones;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Histórico de cierres e informe Z en PDF (feature 048, US5 / FR-015-FR-016).
 *
 * Listado client-side: es un cierre por día, unas centenas de filas al año por tenant.
 */
class CajaCierreController extends Controller
{
    public function index(Request $request): View|JsonResponse
    {
        if (! $request->wantsJson()) {
            return view('pos.caja-cierres');
        }

        $tenantId = (int) tenant()->getTenantKey();

        $cierres = CajaSesion::query()
            ->where('tenant_id', $tenantId)
            ->cerrada()
            ->with(['abiertaPor:id,name', 'cerradaPor:id,name'])
            ->orderByDesc('cerrada_at')
            ->orderByDesc('id')
            ->get();

        // Resumen del mes en curso (en la zona del tenant) para las cards de cabecera.
        $inicioMes = now()->enZonaTenant()->startOfMonth()->utc();
        $delMes = $cierres->filter(fn (CajaSesion $s) => $s->cerrada_at->greaterThanOrEqualTo($inicioMes));

        return response()->json([
            'data' => $cierres->map(fn (CajaSesion $s) => [
                'id' => $s->id,
                'abierta_at' => $s->abierta_at->enZonaTenant()->format('Y-m-d H:i'),
                'cerrada_at' => $s->cerrada_at->enZonaTenant()->format('Y-m-d H:i'),
                'abierta_por' => $s->abiertaPor?->name,
                'cerrada_por' => $s->cerradaPor?->name,
                'num_tickets' => (int) $s->num_tickets,
                'total_facturado' => (string) $s->total_facturado,
                'efectivo_esperado' => (string) $s->efectivo_esperado,
                'efectivo_contado' => (string) $s->efectivo_contado,
                'descuadre' => (string) $s->descuadre,
                'estado' => $s->estadoArqueo(),
                'observacion' => $s->observacion,
                'informe_url_ticket' => route('pos.caja.informe', ['sesion' => $s->id, 'formato' => 'ticket']),
                'informe_url_a4' => route('pos.caja.informe', ['sesion' => $s->id, 'formato' => 'a4']),
            ])->values(),
            'resumen_mes' => [
                'cierres' => $delMes->count(),
                'facturado' => DenominacionesEuro::aDecimal($delMes->sum(fn ($s) => DenominacionesEuro::aCentimos($s->total_facturado))),
                'descuadre' => DenominacionesEuro::aDecimal($delMes->sum(fn ($s) => DenominacionesEuro::aCentimos($s->descuadre))),
            ],
        ]);
    }

    public function informe(Request $request, string $sesion): Response
    {
        // Resolución manual bajo el scope de tenant (nunca binding implícito) y solo sesiones
        // cerradas: una caja abierta no tiene informe Z, se consulta en pantalla.
        $modelo = CajaSesion::query()
            ->where('tenant_id', (int) tenant()->getTenantKey())
            ->cerrada()
            ->findOrFail($sesion);

        $informe = CajaController::payloadInforme($modelo);

        // El informe es interno y sale entero en el idioma del POS (research D9): en chino, o con
        // algún dato chino (motivos, nombres), necesita la fuente CJK (feature 050).
        $fuenteCjk = CargadorTraducciones::esIdiomaTraducido(app()->getLocale())
            || Bilingue::contieneCjk(json_encode($informe, JSON_UNESCAPED_UNICODE), tenant()->nombre_comercial, tenant()->razon_social);

        if ($fuenteCjk) {
            Bilingue::prepararCarpetaFuentes();
        }

        $datos = ['informe' => $informe, 'tenant' => tenant(), 'fuenteCjk' => $fuenteCjk];
        $nombre = 'cierre-caja-'.$modelo->id.'.pdf';

        if ($request->query('formato') === 'a4') {
            return Pdf::loadView('caja.informe-a4', $datos)->setOption('isFontSubsettingEnabled', $fuenteCjk)->stream($nombre);
        }

        // Alto variable en función del contenido; DomPDF recorta el sobrante en blanco.
        $filas = count($informe['por_metodo']) + count($informe['por_impuesto']) + count($informe['anulados'])
            + count($informe['movimientos']) + count($informe['conteo']);
        $altoPuntos = 620 + ($filas * 14) + ($informe['observacion'] ? 60 : 0);

        return Pdf::loadView('caja.informe-80mm', $datos)
            ->setPaper([0, 0, 226.77, $altoPuntos])
            ->setOption('isFontSubsettingEnabled', $fuenteCjk)
            ->stream($nombre);
    }
}
