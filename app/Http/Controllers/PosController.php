<?php

namespace App\Http\Controllers;

use App\Enums\TipoArticulo;
use App\Enums\TipoFactura;
use App\Exceptions\TicketFueraDeTopeException;
use App\Http\Requests\StoreTicketRequest;
use App\Models\Articulo;
use App\Models\Cliente;
use App\Models\Factura;
use App\Services\RegistroTicket;
use App\Support\TiposImpositivos;
use App\Support\TopeSimplificada;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

    public function create(): View
    {
        // Solo productos: un ticket de TPV no factura servicios (regla de negocio).
        $articulos = Articulo::where('tipo', TipoArticulo::Producto)
            ->with('categoria:id,nombre')
            ->orderBy('nombre')
            ->get();

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

        return view('pos.create', [
            'articulos' => $articulos,
            'categorias' => $categorias,
            'clientes' => Cliente::orderBy('nombre')->get(),
            'topeAplicable' => $this->tope->topePara(),
            'regimen' => TiposImpositivos::payloadVista(tenant()->regimen_impositivo),
        ]);
    }

    public function store(StoreTicketRequest $request): RedirectResponse|JsonResponse
    {
        try {
            $ticket = $this->registroTicket->registrar($request->validated());
        } catch (TicketFueraDeTopeException $e) {
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
