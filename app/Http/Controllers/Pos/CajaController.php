<?php

namespace App\Http\Controllers\Pos;

use App\Exceptions\CajaCerradaException;
use App\Exceptions\CajaYaAbiertaException;
use App\Exceptions\CajaYaCerradaException;
use App\Exceptions\ObservacionRequeridaException;
use App\Http\Controllers\Controller;
use App\Http\Requests\AbrirCajaRequest;
use App\Http\Requests\CerrarCajaRequest;
use App\Http\Requests\MovimientoCajaRequest;
use App\Models\CajaSesion;
use App\Services\AperturaCaja;
use App\Services\CierreCaja;
use App\Services\MovimientosCaja;
use App\Services\ResumenCaja;
use App\Support\ConfigPos;
use App\Support\DenominacionesEuro;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * Pantalla de caja del POS (feature 048): estado, apertura, movimientos y cierre con arqueo.
 *
 * **Arqueo ciego (research D6)**: mientras la sesión está abierta, ninguna respuesta de este
 * controlador incluye el efectivo esperado. Solo lo devuelve el cierre, una vez contado. Si viajara
 * "oculto" al navegador, cualquiera lo leería en las herramientas de desarrollo.
 */
class CajaController extends Controller
{
    public function __construct(
        private readonly AperturaCaja $apertura,
        private readonly MovimientosCaja $movimientos,
        private readonly CierreCaja $cierre,
        private readonly ResumenCaja $resumen,
    ) {}

    public function index(Request $request): View|JsonResponse
    {
        $estado = $this->estado();

        if ($request->wantsJson()) {
            return response()->json($estado);
        }

        return view('pos.caja', [
            'estado' => $estado,
            'billetes' => DenominacionesEuro::billetes(),
            'monedas' => DenominacionesEuro::monedas(),
            'tenantNombre' => tenant()->nombre_comercial ?: tenant()->razon_social,
        ]);
    }

    public function abrir(AbrirCajaRequest $request): JsonResponse
    {
        try {
            $sesion = $this->apertura->abrir(
                $request->user(),
                $request->filled('conteo') ? null : (string) $request->validated('fondo_inicial'),
                $request->filled('conteo') ? $request->validated('conteo') : null,
            );
        } catch (CajaYaAbiertaException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'codigo' => CajaYaAbiertaException::CODIGO,
                'sesion' => $e->sesion ? $this->payloadSesion($e->sesion) : null,
            ], 409);
        }

        $sesion->load('abiertaPor:id,name');

        return response()->json([
            'message' => __('Caja abierta.'),
            'sesion' => $this->payloadSesion($sesion),
            'en_vivo' => $this->enVivo($sesion),
        ], 201);
    }

    public function movimiento(MovimientoCajaRequest $request): JsonResponse
    {
        try {
            $movimiento = $this->movimientos->registrar(
                $request->user(),
                $request->validated('tipo'),
                (string) $request->validated('importe'),
                trim((string) $request->validated('motivo')),
            );
        } catch (CajaCerradaException $e) {
            return response()->json(['message' => $e->getMessage(), 'codigo' => CajaCerradaException::CODIGO], 409);
        }

        $sesion = $movimiento->sesion;

        return response()->json([
            'message' => $movimiento->tipo === 'entrada' ? __('Entrada registrada.') : __('Salida registrada.'),
            'movimiento' => [
                'tipo' => $movimiento->tipo,
                'importe' => DenominacionesEuro::aDecimal(DenominacionesEuro::aCentimos($movimiento->importe)),
                'motivo' => $movimiento->motivo,
                'usuario' => $request->user()->name,
                'at' => $movimiento->created_at->enZonaTenant()->toIso8601String(),
            ],
            'en_vivo' => $this->enVivo($sesion),
        ], 201);
    }

    public function cerrar(CerrarCajaRequest $request): JsonResponse
    {
        try {
            $sesion = $this->cierre->cerrar(
                usuario: $request->user(),
                sesionId: (int) $request->validated('sesion_id'),
                conteo: $request->filled('conteo') ? $request->validated('conteo') : null,
                contado: $request->filled('conteo') ? null : (string) $request->validated('efectivo_contado'),
                observacion: $request->validated('observacion'),
            );
        } catch (ObservacionRequeridaException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'codigo' => ObservacionRequeridaException::CODIGO,
                'resultado' => $e->resultado,
                'umbral' => number_format(ConfigPos::cajaUmbralDescuadre((int) tenant()->getTenantKey()), 2, '.', ''),
            ], 422);
        } catch (CajaYaCerradaException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'codigo' => CajaYaCerradaException::CODIGO,
                'informe_url_ticket' => route('pos.caja.informe', ['sesion' => $e->sesion->id, 'formato' => 'ticket']),
                'informe_url_a4' => route('pos.caja.informe', ['sesion' => $e->sesion->id, 'formato' => 'a4']),
            ], 409);
        } catch (CajaCerradaException $e) {
            return response()->json(['message' => $e->getMessage(), 'codigo' => CajaCerradaException::CODIGO], 409);
        }

        return response()->json([
            'message' => __('Caja cerrada.'),
            'sesion_id' => $sesion->id,
            'resultado' => [
                'efectivo_esperado' => (string) $sesion->efectivo_esperado,
                'efectivo_contado' => (string) $sesion->efectivo_contado,
                'descuadre' => (string) $sesion->descuadre,
                'estado' => $sesion->estadoArqueo(),
            ],
            'informe' => self::payloadInforme($sesion),
            'informe_url_ticket' => route('pos.caja.informe', ['sesion' => $sesion->id, 'formato' => 'ticket']),
            'informe_url_a4' => route('pos.caja.informe', ['sesion' => $sesion->id, 'formato' => 'a4']),
            'ultimo_cierre' => $this->payloadUltimoCierre($sesion),
        ]);
    }

    // ── Payloads ─────────────────────────────────────────────────────────

    /** @return array<string, mixed> */
    private function estado(): array
    {
        $sesion = AperturaCaja::sesionAbierta();

        if ($sesion) {
            return [
                'abierta' => true,
                'sesion' => $this->payloadSesion($sesion),
                'en_vivo' => $this->enVivo($sesion),
            ];
        }

        $ultimo = CajaSesion::query()
            ->where('tenant_id', (int) tenant()->getTenantKey())
            ->cerrada()
            ->with('cerradaPor:id,name')
            ->orderByDesc('cerrada_at')
            ->orderByDesc('id')
            ->first();

        return [
            'abierta' => false,
            'ultimo_cierre' => $ultimo ? $this->payloadUltimoCierre($ultimo) : null,
        ];
    }

    /** @return array<string, mixed> */
    private function payloadSesion(CajaSesion $sesion): array
    {
        $abiertaLocal = $sesion->abierta_at->enZonaTenant();
        $hoyLocal = now()->enZonaTenant();

        return [
            'id' => $sesion->id,
            'fondo_inicial' => DenominacionesEuro::aDecimal(DenominacionesEuro::aCentimos($sesion->fondo_inicial)),
            'abierta_por' => $sesion->abiertaPor?->name,
            'abierta_at' => $abiertaLocal->toIso8601String(),
            'abierta_hora' => $abiertaLocal->format('H:i'),
            'abierta_fecha' => $abiertaLocal->format('d/m/Y'),
            'abierta_dia_anterior' => $abiertaLocal->toDateString() !== $hoyLocal->toDateString(),
        ];
    }

    /**
     * Informe X: lo vendido y los movimientos. **Sin** efectivo esperado (D6).
     *
     * @return array<string, mixed>
     */
    private function enVivo(CajaSesion $sesion): array
    {
        $calculo = $this->resumen->calcular($sesion);

        return [
            'num_tickets' => $calculo['num_tickets'],
            'total_vendido' => $calculo['total_facturado'],
            'ticket_medio' => $calculo['ticket_medio'],
            'por_metodo' => $calculo['resumen']['por_metodo'],
            'movimientos' => array_map(fn (array $m) => array_merge($m, [
                'at' => Carbon::parse($m['at'])->enZonaTenant()->toIso8601String(),
            ]), $calculo['resumen']['movimientos']),
        ];
    }

    /** @return array<string, mixed> */
    private function payloadUltimoCierre(CajaSesion $sesion): array
    {
        $sesion->loadMissing('cerradaPor:id,name');
        $cerrada = $sesion->cerrada_at->enZonaTenant();

        return [
            'id' => $sesion->id,
            'cerrada_at' => $cerrada->toIso8601String(),
            'cerrada_texto' => $cerrada->isToday() ? __('hoy :hora', ['hora' => $cerrada->format('H:i')])
                : ($cerrada->isYesterday() ? __('ayer :hora', ['hora' => $cerrada->format('H:i')]) : $cerrada->format('d/m/Y H:i')),
            'cerrada_por' => $sesion->cerradaPor?->name,
            'total_facturado' => (string) $sesion->total_facturado,
            'descuadre' => (string) $sesion->descuadre,
            'estado' => $sesion->estadoArqueo(),
            'informe_url_ticket' => route('pos.caja.informe', ['sesion' => $sesion->id, 'formato' => 'ticket']),
            'informe_url_a4' => route('pos.caja.informe', ['sesion' => $sesion->id, 'formato' => 'a4']),
        ];
    }

    /**
     * El informe Z de una sesión cerrada, **siempre desde lo congelado** (FR-017). Lo comparten la
     * respuesta del cierre (tira de papel en pantalla) y las plantillas PDF.
     *
     * @return array<string, mixed>
     */
    public static function payloadInforme(CajaSesion $sesion): array
    {
        $sesion->loadMissing(['abiertaPor:id,name', 'cerradaPor:id,name']);
        $resumen = $sesion->resumen ?? [];

        return [
            'numero' => $sesion->id,
            'abierta_por' => $sesion->abiertaPor?->name,
            'abierta_at' => $sesion->abierta_at->enZonaTenant()->format('d/m/Y H:i'),
            'cerrada_por' => $sesion->cerradaPor?->name,
            'cerrada_at' => $sesion->cerrada_at?->enZonaTenant()->format('d/m/Y H:i'),
            'num_tickets' => (int) $sesion->num_tickets,
            'total_facturado' => (string) $sesion->total_facturado,
            'fondo_inicial' => (string) $sesion->fondo_inicial,
            'efectivo_ventas' => (string) $sesion->efectivo_ventas,
            'entradas' => (string) $sesion->entradas,
            'salidas' => (string) $sesion->salidas,
            'efectivo_esperado' => (string) $sesion->efectivo_esperado,
            'efectivo_contado' => (string) $sesion->efectivo_contado,
            'descuadre' => (string) $sesion->descuadre,
            'estado' => $sesion->estadoArqueo(),
            'observacion' => $sesion->observacion,
            'conteo' => collect($sesion->conteo_cierre ?? [])
                ->map(fn ($cantidad, $centimos) => [
                    'centimos' => (int) $centimos,
                    'cantidad' => (int) $cantidad,
                    'subtotal' => DenominacionesEuro::aDecimal((int) $centimos * (int) $cantidad),
                ])
                ->sortByDesc('centimos')
                ->values()
                ->all(),
            'por_metodo' => $resumen['por_metodo'] ?? [],
            'por_impuesto' => $resumen['por_impuesto'] ?? [],
            'primer_ticket' => $resumen['primer_ticket'] ?? null,
            'ultimo_ticket' => $resumen['ultimo_ticket'] ?? null,
            'anulados' => $resumen['anulados'] ?? [],
            'movimientos' => array_map(fn (array $m) => array_merge($m, [
                'hora' => Carbon::parse($m['at'])->enZonaTenant()->format('H:i'),
            ]), $resumen['movimientos'] ?? []),
        ];
    }
}
