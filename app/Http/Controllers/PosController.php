<?php

namespace App\Http\Controllers;

use App\Enums\TipoArticulo;
use App\Enums\TipoFactura;
use App\Exceptions\PagoTicketDescuadradoException;
use App\Exceptions\TicketFueraDeTopeException;
use App\Http\Requests\StoreTicketRequest;
use App\Models\Articulo;
use App\Models\Cliente;
use App\Models\Factura;
use App\Models\PosCuenta;
use App\Models\PosMesa;
use App\Services\RegistroTicket;
use App\Support\ConfigPos;
use App\Support\TiposImpositivos;
use App\Support\TopeSimplificada;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class PosController extends Controller
{
    public function __construct(
        private readonly RegistroTicket $registroTicket,
        private readonly TopeSimplificada $tope,
    ) {}

    public function index(Request $request): View|JsonResponse
    {
        if ($request->wantsJson()) {
            $tickets = Factura::where('tipo', TipoFactura::Simplificada)
                ->with('pagosTicket')
                ->orderByDesc('fecha_expedicion')
                ->orderByDesc('id')
                ->get();

            return response()->json([
                'data' => $tickets->map(function (Factura $ticket) {
                    $cualificada = (bool) $ticket->cliente_nif;

                    return [
                        'id' => $ticket->id,
                        'identificador' => $ticket->numero_completo ?? 'Borrador',
                        'estado' => $ticket->estado->value,
                        'cualificada' => $cualificada,
                        'receptor' => $cualificada
                            ? ($ticket->cliente_razon_social ?: $ticket->cliente_nombre ?: $ticket->cliente_nif)
                            : 'Consumidor final',
                        'fecha_expedicion' => $ticket->fecha_expedicion->toDateString(),
                        'total' => number_format((float) $ticket->total, 2, '.', ''),
                        // Desglose interno de cómo se cobró en caja (pago simple o dividido).
                        'pagos' => $ticket->pagosTicket->map(fn ($pago) => [
                            'metodo' => $pago->metodo->value,
                            'metodo_label' => ucfirst($pago->metodo->value),
                            'importe' => number_format((float) $pago->importe, 2, '.', ''),
                        ])->values(),
                        'dividido' => $ticket->pagosTicket->count() > 1,
                        'pdf_ticket_url' => route('pos.pdf', ['factura' => $ticket->id, 'formato' => 'ticket']),
                        'pdf_a4_url' => route('pos.pdf', ['factura' => $ticket->id, 'formato' => 'a4']),
                    ];
                })->values(),
                'totales' => [
                    'total' => $tickets->count(),
                    'importe_total' => number_format((float) $tickets->sum('total'), 2, '.', ''),
                ],
            ]);
        }

        return view('pos.index');
    }

    public function create(Request $request): View
    {
        $tenantId = (int) tenant()->getTenantKey();
        $hosteleria = ConfigPos::hosteleriaActivo($tenantId);
        $opcionesActivas = ConfigPos::opcionesActivo($tenantId);

        // Solo productos: un ticket de TPV no factura servicios (regla de negocio).
        $articulos = Articulo::where('tipo', TipoArticulo::Producto)
            ->with('categoria:id,nombre')
            ->orderBy('nombre')
            ->get();

        // Qué artículos abren modal de opciones, resuelto de una vez con el índice
        // `pos_articulo_opcion(articulo_id)` — SC-004 exige que un artículo SIN opciones se añada
        // en un solo toque, así que no puede haber una consulta por toque. Con la capacidad
        // apagada el conjunto queda vacío y `tiene_opciones` es siempre false.
        $conOpciones = $opcionesActivas
            ? DB::table('pos_articulo_opcion')
                ->where('tenant_id', $tenantId)
                ->distinct()
                ->pluck('articulo_id')
                ->all()
            : [];

        $articulos->each(function (Articulo $articulo) use ($conOpciones) {
            $articulo->tiene_opciones = in_array($articulo->id, $conOpciones, true);
        });

        // Filtros del catálogo: solo categorías que tienen al menos un producto (con su conteo),
        // ordenadas por nombre. Se renderizan como botones grandes tablet-first.
        $categorias = $articulos
            ->filter(fn (Articulo $a) => $a->categoria !== null)
            ->groupBy('categoria_id')
            ->map(fn ($grupo) => [
                'id' => $grupo->first()->categoria_id,
                'nombre' => $grupo->first()->categoria->nombre,
                'total' => $grupo->count(),
            ])
            ->sortBy('nombre')
            ->values();

        // `?cuenta={id}` precarga una cuenta abierta; `?mesa={id}` viene de tocar una mesa libre
        // en la sala (la cuenta se crea recién al guardar la primera línea, para no dejar cuentas
        // vacías por cada toque). Resolución manual bajo TenantScope, nunca binding implícito.
        $cuenta = null;
        if ($hosteleria && $request->filled('cuenta')) {
            $cuenta = PosCuenta::query()
                ->with('lineas.opciones', 'mesa.zona')
                ->where('estado', PosCuenta::ESTADO_ABIERTA)
                ->find($request->query('cuenta'));
        }

        $mesa = null;
        if ($hosteleria && $cuenta === null && $request->filled('mesa')) {
            $mesa = PosMesa::query()->with('zona')->find($request->query('mesa'));
        }

        $mesaPreseleccionada = $mesa ?? $cuenta?->mesa;

        return view('pos.create', [
            'articulos' => $articulos,
            'categorias' => $categorias,
            'clientes' => Cliente::orderBy('nombre')->get(),
            'topeAplicable' => $this->tope->topePara(),
            'regimen' => TiposImpositivos::payloadVista(tenant()->regimen_impositivo),
            'hosteleriaActiva' => $hosteleria,
            'opcionesActivas' => $opcionesActivas,
            'cobroDivididoActivo' => ConfigPos::cobroDivididoActivo($tenantId),
            'suplementoZonaActivo' => ConfigPos::suplementoZonaActivo($tenantId),
            'cuentaPrecargada' => $cuenta,
            'mesaPreseleccionada' => $mesaPreseleccionada,
            // Payloads ya en forma de array plano para el `@json(...)` de la vista: construir
            // arrays con arrow functions dentro de un directivo Blade confunde su extractor de
            // argumentos (corta en el primer `)` que encuentra), así que se arman aquí.
            'salaUrlPayload' => $hosteleria ? route('pos.sala') : null,
            'cuentaPayload' => $cuenta ? [
                'id' => $cuenta->id,
                'version' => $cuenta->version,
                'mesa_id' => $cuenta->mesa_id,
                'mesa_nombre' => $cuenta->mesa?->nombre,
                'pendiente' => number_format($cuenta->pendiente(), 2, '.', ''),
                'zona_suplemento' => ConfigPos::suplementoZonaActivo($tenantId)
                    ? number_format((float) ($cuenta->mesa?->zona?->suplemento_porcentaje ?? 0), 2, '.', '')
                    : '0.00',
            ] : null,
            'mesaPreseleccionadaPayload' => $mesaPreseleccionada ? [
                'id' => $mesaPreseleccionada->id,
                'nombre' => $mesaPreseleccionada->nombre,
            ] : null,
            'lineasPrecargadasPayload' => $cuenta ? $cuenta->lineas->map(fn ($l) => [
                'cuenta_linea_id' => $l->id,
                'articulo_id' => $l->articulo_id,
                'concepto' => $l->concepto,
                'unidad' => $l->unidad,
                // Precio unitario efectivo (base + suplemento de opciones), igual que arma
                // `pos-ticket.js` al añadir un artículo con opciones desde el modal.
                'precio' => $l->precioEfectivo(),
                'tipo' => (float) $l->tipo_impositivo,
                'cantidad' => (float) $l->cantidad,
                'opciones' => $l->opciones->map(fn ($o) => [
                    'opcion_id' => $o->opcion_id,
                    'nombre' => $o->nombre,
                    'precio' => (float) $o->precio,
                ])->values(),
            ])->values() : null,
        ]);
    }

    /**
     * Opciones aplicables a un artículo, para el modal de selección del TPV (FR-042/FR-043). Se
     * pide solo al tocar un artículo marcado con `tiene_opciones`, nunca en cada toque.
     */
    public function opcionesArticulo(string $articulo): JsonResponse
    {
        $tenantId = (int) tenant()->getTenantKey();

        if (! ConfigPos::opcionesActivo($tenantId)) {
            return response()->json(['grupos' => []]);
        }

        $modelo = Articulo::query()->with(['posGrupos', 'posOpciones.grupo'])->findOrFail($articulo);

        $porGrupo = $modelo->posOpciones->groupBy('grupo_id');

        $grupos = $modelo->posGrupos->map(fn ($grupo) => [
            'grupo_id' => $grupo->id,
            'nombre' => $grupo->nombre,
            'obligatorio' => (bool) $grupo->obligatorio,
            'min_selecciones' => (int) $grupo->min_selecciones,
            'max_selecciones' => $grupo->max_selecciones !== null ? (int) $grupo->max_selecciones : null,
            'opciones' => ($porGrupo->get($grupo->id) ?? collect())->map(fn ($opcion) => [
                'opcion_id' => $opcion->id,
                'nombre' => $opcion->nombre,
                // Precio del pivot: el de ESTE artículo (FR-039). Es informativo para la interfaz;
                // el importe real lo vuelve a calcular el servidor al guardar y al cobrar.
                'precio' => (float) $opcion->pivot->precio,
            ])->values(),
        ])->filter(fn ($grupo) => $grupo['opciones']->isNotEmpty())->values();

        return response()->json(['grupos' => $grupos]);
    }

    public function store(StoreTicketRequest $request): RedirectResponse|JsonResponse
    {
        try {
            $ticket = $this->registroTicket->registrar($request->validated());
        } catch (TicketFueraDeTopeException|PagoTicketDescuadradoException $e) {
            if ($request->wantsJson()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return redirect()->back()->with('error', $e->getMessage());
        }

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Ticket emitido correctamente.',
                'id' => $ticket->id,
                'numero_completo' => $ticket->numero_completo,
            ], 201);
        }

        return redirect()->route('pos.index')->with('success', 'Ticket emitido correctamente.');
    }

    public function pdf(Request $request, string $factura): Response
    {
        // Resolución manual bajo el scope de tenant (no binding implícito): el ticket sólo se
        // encuentra si pertenece al tenant activo.
        $ticket = Factura::where('tipo', TipoFactura::Simplificada)
            ->with(['lineas', 'impuestos', 'cliente', 'tenant'])
            ->findOrFail($factura);

        $formato = $request->query('formato') === 'a4' ? 'a4' : 'ticket';

        if ($formato === 'a4') {
            $pdf = Pdf::loadView('facturas.pdf', ['factura' => $ticket]);
        } else {
            // Rollo de 80 mm de ancho (≈ 226.77 pt). Alto amplio; DomPDF recorta el sobrante en blanco.
            $altoPuntos = 400 + (count($ticket->lineas) * 24);
            $pdf = Pdf::loadView('facturas.ticket-80mm', ['factura' => $ticket])
                ->setPaper([0, 0, 226.77, $altoPuntos]);
        }

        return $pdf->stream(($ticket->numero_completo ?? 'ticket-'.$ticket->id).'.pdf');
    }
}
