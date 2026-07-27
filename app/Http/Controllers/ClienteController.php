<?php

namespace App\Http\Controllers;

use App\Enums\AccionLogActividad;
use App\Enums\EntidadLogActividad;
use App\Enums\EstadoFactura;
use App\Enums\TipoCliente;
use App\Enums\TipoFactura;
use App\Http\Requests\StoreClienteRequest;
use App\Http\Requests\UpdateClienteRequest;
use App\Models\Albaran;
use App\Models\Cliente;
use App\Models\Factura;
use App\Models\Oportunidad;
use App\Models\Pago;
use App\Models\Presupuesto;
use App\Models\Provincia;
use App\Services\RegistradorActividad;
use App\Support\Formato;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ClienteController extends Controller
{
    public function __construct(
        private readonly RegistradorActividad $registradorActividad,
    ) {}

    public function index(Request $request): View|JsonResponse
    {
        if ($request->wantsJson()) {
            $clientes = Cliente::orderBy('nombre')->get();

            return response()->json([
                'data' => $clientes->map(fn (Cliente $cliente) => [
                    'id' => $cliente->id,
                    'nombre' => $cliente->razon_social ?: $cliente->nombre,
                    'tipo' => $cliente->tipo->value,
                    'tipo_label' => $cliente->tipo === TipoCliente::Empresa ? 'Empresa' : 'Particular',
                    'nif' => $cliente->nif,
                    'email' => $cliente->email,
                    'telefono' => $cliente->telefono,
                    'ciudad' => $cliente->ciudad,
                    'razon_social' => $cliente->razon_social,
                    'direccion' => $cliente->direccion,
                    'cp' => $cliente->cp,
                    'provincia' => $cliente->provincia,
                    'pais' => $cliente->pais,
                    'recargo' => $cliente->aplica_recargo_equivalencia,
                    'notas' => $cliente->notas,
                    'perfil_url' => route('clientes.show', $cliente),
                    'update_url' => route('clientes.update', $cliente),
                    'delete_url' => route('clientes.destroy', $cliente),
                ])->values(),
                'totales' => [
                    'total' => $clientes->count(),
                    'empresas' => $clientes->where('tipo', TipoCliente::Empresa)->count(),
                    'particulares' => $clientes->where('tipo', TipoCliente::Particular)->count(),
                ],
            ]);
        }

        return view('clientes.index', [
            'provincias' => Provincia::orderBy('nombre')->get(['id', 'nombre']),
        ]);
    }

    public function store(StoreClienteRequest $request): RedirectResponse|JsonResponse
    {
        $cliente = Cliente::create($request->validated());

        $this->registradorActividad->registrar(
            auth()->user(),
            AccionLogActividad::Alta,
            EntidadLogActividad::Cliente,
            $cliente->id,
            "Creó el cliente {$cliente->nombre}",
        );

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Cliente creado correctamente.'], 201);
        }

        return redirect()->route('clientes.index')->with('success', 'Cliente creado correctamente.');
    }

    /**
     * Perfil del cliente (feature 035). En HTML devuelve la vista con la ficha, el resumen
     * financiero y los datos de los gráficos; las pestañas de documentos son DataTables que se
     * alimentan de esta misma ruta con `?recurso=<tipo>` (convención "Listados: SIEMPRE
     * DataTable" de docs/04-front-guidelines.md).
     */
    public function show(Request $request, string $cliente): View|JsonResponse
    {
        // No se usa binding implícito de ruta: ver comentario en update()/destroy() sobre
        // SubstituteBindings vs TenantScope.
        $cliente = Cliente::findOrFail($cliente);

        if ($request->wantsJson()) {
            return $this->recursoDelPerfil($request->query('recurso'), $cliente);
        }

        $puedeVerFacturas = Auth::user()->can('ver-facturas');

        return view('clientes.show', [
            'cliente' => $cliente,
            'provincias' => Provincia::orderBy('nombre')->get(['id', 'nombre']),
            'resumen' => $puedeVerFacturas ? $this->resumenFinanciero($cliente) : null,
            'graficos' => $puedeVerFacturas ? $this->graficosFinancieros($cliente) : null,
        ]);
    }

    /**
     * Facturas del cliente que cuentan para el perfil: se excluyen las simplificadas (viven en su
     * propio módulo POS), mismo criterio que FacturaController::index.
     */
    private function facturasDelPerfil(Cliente $cliente): HasMany
    {
        return $cliente->facturas()->where('tipo', '!=', TipoFactura::Simplificada->value);
    }

    /**
     * Payload JSON de la pestaña pedida, ya filtrado por el permiso del módulo correspondiente
     * (FR-013: sin el permiso, la pestaña no existe ni devuelve datos).
     */
    private function recursoDelPerfil(?string $recurso, Cliente $cliente): JsonResponse
    {
        $usuario = Auth::user();

        $permisos = [
            'facturas' => 'ver-facturas',
            'presupuestos' => 'ver-presupuestos',
            'albaranes' => 'ver-albaranes',
            'oportunidades' => 'ver-oportunidades',
            // Combinado: cada tipo se filtra por su propio permiso dentro de timeline().
            'actividad' => null,
        ];

        if ($recurso === null || ! array_key_exists($recurso, $permisos)) {
            abort(404);
        }

        if ($permisos[$recurso] !== null && ! $usuario->can($permisos[$recurso])) {
            abort(403);
        }

        return response()->json(['data' => match ($recurso) {
            'facturas' => $this->facturasDelPerfil($cliente)
                ->orderByDesc('fecha_expedicion')
                ->get()
                ->map(fn (Factura $factura) => [
                    'identificador' => $factura->numero_completo ?? 'Borrador',
                    'fecha' => $factura->fecha_expedicion?->format('d/m/Y'),
                    'fecha_orden' => $factura->fecha_expedicion?->toDateString(),
                    'total' => Formato::moneda($factura->totalCobrable()),
                    'total_orden' => $factura->totalCobrable(),
                    'estado' => $factura->estado->value,
                    'estado_cobro' => $factura->estadoCobro()->value,
                    'pdf_url' => route('facturas.pdf', $factura),
                ])->values(),

            'presupuestos' => $cliente->presupuestos()
                ->orderByDesc('fecha_emision')
                ->get()
                ->map(fn (Presupuesto $presupuesto) => [
                    'identificador' => $presupuesto->numero,
                    'fecha' => $presupuesto->fecha_emision?->format('d/m/Y'),
                    'fecha_orden' => $presupuesto->fecha_emision?->toDateString(),
                    'total' => Formato::moneda($presupuesto->total),
                    'total_orden' => (float) $presupuesto->total,
                    'estado' => $presupuesto->estado->value,
                    'estado_label' => $presupuesto->estado->label(),
                    'pdf_url' => route('presupuestos.pdf', $presupuesto),
                ])->values(),

            'albaranes' => $cliente->albaranes()
                ->orderByDesc('fecha_entrega')
                ->get()
                ->map(fn (Albaran $albaran) => [
                    'identificador' => $albaran->numero,
                    'fecha' => $albaran->fecha_entrega?->format('d/m/Y'),
                    'fecha_orden' => $albaran->fecha_entrega?->toDateString(),
                    'total' => Formato::moneda($albaran->total),
                    'total_orden' => (float) $albaran->total,
                    'estado' => $albaran->estado->value,
                    'estado_label' => $albaran->estado->label(),
                    'show_url' => route('albaranes.show', $albaran),
                ])->values(),

            'oportunidades' => $cliente->oportunidades()
                ->orderByDesc('created_at')
                ->get()
                ->map(fn (Oportunidad $oportunidad) => [
                    'titulo' => $oportunidad->titulo,
                    'etapa' => $oportunidad->etapa->value,
                    'etapa_label' => $oportunidad->etapa->label(),
                    'importe' => Formato::moneda($oportunidad->importe_estimado),
                    'importe_orden' => (float) $oportunidad->importe_estimado,
                    'show_url' => route('oportunidades.show', $oportunidad),
                ])->values(),

            'actividad' => $this->timeline($cliente),
        }]);
    }

    /**
     * Línea de tiempo combinada: los 10 eventos más recientes de cada tipo que el usuario tenga
     * permiso de ver, mezclados y ordenados por fecha descendente (se muestran los 20 últimos).
     *
     * @return list<array{fecha: string, fecha_orden: string, tipo: string, etiqueta: string, url: string, es_pdf: bool}>
     */
    private function timeline(Cliente $cliente): array
    {
        $usuario = Auth::user();
        $eventos = collect();

        if ($usuario->can('ver-facturas')) {
            foreach ($this->facturasDelPerfil($cliente)->orderByDesc('fecha_expedicion')->limit(10)->get() as $factura) {
                $eventos->push([
                    'fecha_carbon' => $factura->fecha_expedicion,
                    'tipo' => 'Factura',
                    'etiqueta' => 'Factura '.($factura->numero_completo ?? 'en borrador'),
                    'url' => route('facturas.pdf', $factura),
                    'es_pdf' => true,
                ]);
            }
        }

        if ($usuario->can('ver-presupuestos')) {
            foreach ($cliente->presupuestos()->orderByDesc('fecha_emision')->limit(10)->get() as $presupuesto) {
                $eventos->push([
                    'fecha_carbon' => $presupuesto->fecha_emision,
                    'tipo' => 'Presupuesto',
                    'etiqueta' => 'Presupuesto '.$presupuesto->numero.' ('.$presupuesto->estado->label().')',
                    'url' => route('presupuestos.pdf', $presupuesto),
                    'es_pdf' => true,
                ]);
            }
        }

        if ($usuario->can('ver-albaranes')) {
            foreach ($cliente->albaranes()->orderByDesc('fecha_entrega')->limit(10)->get() as $albaran) {
                $eventos->push([
                    'fecha_carbon' => $albaran->fecha_entrega,
                    'tipo' => 'Albarán',
                    'etiqueta' => 'Albarán '.$albaran->numero.' ('.$albaran->estado->label().')',
                    'url' => route('albaranes.show', $albaran),
                    'es_pdf' => false,
                ]);
            }
        }

        if ($usuario->can('ver-oportunidades')) {
            foreach ($cliente->oportunidades()->orderByDesc('created_at')->limit(10)->get() as $oportunidad) {
                $eventos->push([
                    'fecha_carbon' => $oportunidad->created_at,
                    'tipo' => 'Oportunidad',
                    'etiqueta' => 'Oportunidad "'.$oportunidad->titulo.'" ('.$oportunidad->etapa->label().')',
                    'url' => route('oportunidades.show', $oportunidad),
                    'es_pdf' => false,
                ]);
            }
        }

        return $eventos
            ->filter(fn (array $evento) => $evento['fecha_carbon'] !== null)
            ->sortByDesc(fn (array $evento) => $evento['fecha_carbon'])
            ->take(20)
            ->map(fn (array $evento) => [
                'fecha' => $evento['fecha_carbon']->format('d/m/Y'),
                'fecha_orden' => $evento['fecha_carbon']->toDateString(),
                'tipo' => $evento['tipo'],
                'etiqueta' => $evento['etiqueta'],
                'url' => $evento['url'],
                'es_pdf' => $evento['es_pdf'],
            ])
            ->values()
            ->all();
    }

    /**
     * Agregado financiero del cliente (FR-005). Calculado en backend sobre importes ya
     * persistidos por el módulo de facturación (Principio III), sin recalcular impuestos.
     *
     * @return array<string, float|int>
     */
    private function resumenFinanciero(Cliente $cliente): array
    {
        $facturas = $this->facturasDelPerfil($cliente)->get()
            ->reject(fn (Factura $f) => $f->estado === EstadoFactura::Anulada);

        $conSaldo = $facturas->filter(fn (Factura $f) => $f->saldoPendiente() > 0);
        $vencidas = $conSaldo->filter(
            fn (Factura $f) => $f->fecha_vencimiento !== null && $f->fecha_vencimiento->isPast()
        );

        $totalFacturado = round($facturas->sum(fn (Factura $f) => $f->totalCobrable()), 2);
        $cantidad = $facturas->count();

        return [
            'total_facturado' => $totalFacturado,
            'pendiente_cobro' => round($conSaldo->sum(fn (Factura $f) => $f->saldoPendiente()), 2),
            'facturas_vencidas_cantidad' => $vencidas->count(),
            'facturas_vencidas_importe' => round($vencidas->sum(fn (Factura $f) => $f->saldoPendiente()), 2),
            'ticket_medio' => $cantidad > 0 ? round($totalFacturado / $cantidad, 2) : 0.0,
            'cantidad_facturas' => $cantidad,
        ];
    }

    /**
     * Series para los gráficos del resumen financiero: reparto del importe facturado por estado
     * de cobro (dona) y evolución facturado/cobrado de los últimos 6 meses (barras). Mismo stack
     * que el dashboard (Chart.js ya vendorizado), ver public/js/plugins-init/dashboard-charts.init.js.
     *
     * @return array{cobro: list<array{etiqueta: string, importe: float}>, evolucion: list<array{etiqueta: string, facturado: float, cobrado: float}>}
     */
    private function graficosFinancieros(Cliente $cliente): array
    {
        $facturas = $this->facturasDelPerfil($cliente)->get()
            ->reject(fn (Factura $f) => $f->estado === EstadoFactura::Anulada);

        $cobrado = round($facturas->sum(fn (Factura $f) => $f->montoCobrado()), 2);

        $pendienteVencido = round($facturas
            ->filter(fn (Factura $f) => $f->saldoPendiente() > 0
                && $f->fecha_vencimiento !== null && $f->fecha_vencimiento->isPast())
            ->sum(fn (Factura $f) => $f->saldoPendiente()), 2);

        $pendienteAlDia = round($facturas
            ->filter(fn (Factura $f) => $f->saldoPendiente() > 0
                && ! ($f->fecha_vencimiento !== null && $f->fecha_vencimiento->isPast()))
            ->sum(fn (Factura $f) => $f->saldoPendiente()), 2);

        // Evolución: 6 meses hacia atrás incluyendo el actual. "Facturado" por fecha de
        // expedición de la factura; "cobrado" por fecha real de cada pago vigente (no anulado),
        // que es lo que hace comparables ambas series mes a mes.
        $pagos = Pago::whereIn('factura_id', $facturas->pluck('id'))
            ->whereNull('anulado_at')
            ->get();

        $evolucion = [];

        for ($i = 5; $i >= 0; $i--) {
            $mes = now()->startOfMonth()->subMonths($i);
            $clave = $mes->format('Y-m');

            $evolucion[] = [
                'etiqueta' => $mes->translatedFormat('M Y'),
                'facturado' => round($facturas
                    ->filter(fn (Factura $f) => $f->fecha_expedicion?->format('Y-m') === $clave)
                    ->sum(fn (Factura $f) => $f->totalCobrable()), 2),
                'cobrado' => round($pagos
                    ->filter(fn (Pago $p) => $p->fecha?->format('Y-m') === $clave)
                    ->sum(fn (Pago $p) => (float) $p->importe), 2),
            ];
        }

        return [
            'cobro' => [
                ['etiqueta' => 'Cobrado', 'importe' => $cobrado],
                ['etiqueta' => 'Pendiente al día', 'importe' => $pendienteAlDia],
                ['etiqueta' => 'Vencido sin cobrar', 'importe' => $pendienteVencido],
            ],
            'evolucion' => $evolucion,
        ];
    }

    public function update(UpdateClienteRequest $request, string $cliente): RedirectResponse|JsonResponse
    {
        // No se usa binding implícito de ruta: en la práctica SubstituteBindings puede ejecutarse
        // antes que el middleware `tenant.context`, dejando el TenantScope todavía sin inicializar
        // y permitiendo resolver clientes de otro tenant. Se resuelve manualmente aquí, donde el
        // scope ya está garantizado (el controller corre al final del pipeline de middleware).
        $cliente = Cliente::findOrFail($cliente);

        $cliente->update($request->validated());

        $this->registradorActividad->registrar(
            auth()->user(),
            AccionLogActividad::Modificacion,
            EntidadLogActividad::Cliente,
            $cliente->id,
            "Modificó el cliente {$cliente->nombre}",
        );

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Cliente actualizado correctamente.']);
        }

        return redirect()->route('clientes.index')->with('success', 'Cliente actualizado correctamente.');
    }

    public function destroy(Request $request, string $cliente): RedirectResponse|JsonResponse
    {
        $cliente = Cliente::findOrFail($cliente);

        $cliente->delete();

        $this->registradorActividad->registrar(
            auth()->user(),
            AccionLogActividad::Baja,
            EntidadLogActividad::Cliente,
            $cliente->id,
            "Eliminó el cliente {$cliente->nombre}",
        );

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Cliente eliminado correctamente.']);
        }

        return redirect()->route('clientes.index')->with('success', 'Cliente eliminado correctamente.');
    }
}
