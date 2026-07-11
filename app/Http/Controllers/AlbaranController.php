<?php

namespace App\Http\Controllers;

use App\Enums\AccionLogActividad;
use App\Enums\EntidadLogActividad;
use App\Enums\EstadoAlbaran;
use App\Exceptions\AlbaranTransicionInvalidaException;
use App\Exceptions\CantidadAlbaranExcedeLoPendienteException;
use App\Exceptions\ConversionAlbaranesException;
use App\Http\Requests\StoreAlbaranRequest;
use App\Http\Requests\UpdateAlbaranRequest;
use App\Models\Albaran;
use App\Models\Cliente;
use App\Models\Presupuesto;
use App\Services\AnuladorAlbaran;
use App\Services\ConversorAlbaranesFactura;
use App\Services\EntregadorAlbaran;
use App\Services\RegistradorActividad;
use App\Services\RegistroAlbaran;
use App\Support\TiposImpositivos;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AlbaranController extends Controller
{
    public function __construct(
        private readonly RegistroAlbaran $registro,
        private readonly EntregadorAlbaran $entregador,
        private readonly AnuladorAlbaran $anulador,
        private readonly ConversorAlbaranesFactura $conversor,
        private readonly RegistradorActividad $registradorActividad,
    ) {}

    public function index(Request $request): View|JsonResponse
    {
        if ($request->wantsJson()) {
            $albaranes = Albaran::with(['cliente', 'presupuesto'])->orderByDesc('created_at')->get();

            return response()->json([
                'data' => $albaranes->map(fn (Albaran $albaran) => [
                    'id' => $albaran->id,
                    'numero' => $albaran->numero,
                    'receptor' => $albaran->receptor_nombre,
                    'cliente_id' => $albaran->cliente_id,
                    'estado' => $albaran->estado->value,
                    'estado_label' => $albaran->estado->label(),
                    'fecha_entrega' => $albaran->fecha_entrega?->format('d/m/Y'),
                    'total' => (float) $albaran->total,
                    'es_editable' => $albaran->estado->esEditable(),
                    'es_eliminable' => $albaran->estado === EstadoAlbaran::Borrador,
                    'es_anulable' => $albaran->estado === EstadoAlbaran::Entregado,
                    'es_convertible' => $albaran->estado === EstadoAlbaran::Entregado,
                    'show_url' => route('albaranes.show', $albaran),
                    'edit_url' => route('albaranes.edit', $albaran),
                    'delete_url' => route('albaranes.destroy', $albaran),
                    'estado_url' => route('albaranes.estado', $albaran),
                ]),
                'totales' => [
                    'total' => Albaran::count(),
                    'entregados' => Albaran::where('estado', EstadoAlbaran::Entregado)->count(),
                    'pendientes_facturar' => Albaran::where('estado', EstadoAlbaran::Entregado)->count(),
                ],
            ]);
        }

        return view('albaranes.index');
    }

    public function create(Request $request): View
    {
        $presupuestoPreseleccionado = $request->query('presupuesto_id')
            ? Presupuesto::with('lineas')->find($request->query('presupuesto_id'))
            : null;
        $cliente = $request->query('cliente_id') ? Cliente::find($request->query('cliente_id')) : null;

        $presupuestosConPendiente = Presupuesto::with('lineas', 'cliente')
            ->where('estado', 'aceptado')
            ->orderByDesc('fecha_emision')
            ->get()
            ->filter(fn (Presupuesto $p) => $p->lineas->contains(fn ($l) => $l->cantidadPendiente() > 0))
            ->values();

        return view('albaranes.create', [
            'albaran' => null,
            'presupuesto' => $presupuestoPreseleccionado,
            'clientePreseleccionado' => $cliente,
            'clientes' => Cliente::orderBy('nombre')->get(),
            'presupuestos' => $presupuestosConPendiente->map(fn (Presupuesto $p) => [
                'id' => $p->id,
                'numero' => $p->numero,
                'cliente_id' => $p->cliente_id,
                'receptor' => $p->receptor_nombre,
                'lineas' => $p->lineas
                    ->filter(fn ($linea) => $linea->cantidadPendiente() > 0)
                    ->map(fn ($linea) => [
                        'presupuesto_linea_id' => $linea->id,
                        'articulo_id' => $linea->articulo_id,
                        'concepto' => $linea->concepto,
                        'unidad' => $linea->unidad,
                        'cantidad' => $linea->cantidadPendiente(),
                        'cantidad_pendiente' => $linea->cantidadPendiente(),
                        'precio_unitario' => (float) $linea->precio_unitario,
                        'descuento_porcentaje' => $linea->descuento_porcentaje !== null ? (float) $linea->descuento_porcentaje : null,
                        'tipo_impositivo' => (float) $linea->tipo_impositivo,
                    ])->values(),
            ])->values(),
            'regimen' => TiposImpositivos::payloadVista(tenant()->regimen_impositivo),
            'lineasIniciales' => $presupuestoPreseleccionado
                ? $presupuestoPreseleccionado->lineas
                    ->filter(fn ($linea) => $linea->cantidadPendiente() > 0)
                    ->map(fn ($linea) => [
                        'presupuesto_linea_id' => $linea->id,
                        'articulo_id' => $linea->articulo_id,
                        'concepto' => $linea->concepto,
                        'unidad' => $linea->unidad,
                        'cantidad' => $linea->cantidadPendiente(),
                        'cantidad_pendiente' => $linea->cantidadPendiente(),
                        'precio_unitario' => (float) $linea->precio_unitario,
                        'descuento_porcentaje' => $linea->descuento_porcentaje !== null ? (float) $linea->descuento_porcentaje : null,
                        'tipo_impositivo' => (float) $linea->tipo_impositivo,
                    ])->values()
                : [],
        ]);
    }

    public function store(StoreAlbaranRequest $request): RedirectResponse|JsonResponse
    {
        try {
            $albaran = $this->registro->guardar($request->validated());
        } catch (CantidadAlbaranExcedeLoPendienteException $e) {
            if ($request->wantsJson()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        $this->registradorActividad->registrar(
            auth()->user(),
            AccionLogActividad::Alta,
            EntidadLogActividad::Albaran,
            $albaran->id,
            "Creó el albarán {$albaran->numero}",
        );

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Albarán creado correctamente.', 'id' => $albaran->id], 201);
        }

        return redirect()->route('albaranes.show', $albaran)->with('success', 'Albarán creado correctamente.');
    }

    public function show(string $albaran): View
    {
        $albaran = Albaran::with(['lineas.articulo', 'presupuesto', 'cliente', 'facturaConvertida'])->findOrFail($albaran);

        return view('albaranes.show', ['albaran' => $albaran]);
    }

    public function edit(string $albaran): View
    {
        $albaran = Albaran::with('lineas')->findOrFail($albaran);

        if (! $albaran->estado->esEditable()) {
            abort(403, 'Solo se pueden editar albaranes en borrador.');
        }

        return view('albaranes.create', [
            'albaran' => $albaran,
            'presupuesto' => $albaran->presupuesto,
            'clientePreseleccionado' => $albaran->cliente,
            'clientes' => Cliente::orderBy('nombre')->get(),
            'presupuestos' => collect(),
            'regimen' => TiposImpositivos::payloadVista(tenant()->regimen_impositivo),
            'lineasIniciales' => $albaran->lineas->map(fn ($linea) => [
                'presupuesto_linea_id' => $linea->presupuesto_linea_id,
                'articulo_id' => $linea->articulo_id,
                'concepto' => $linea->concepto,
                'unidad' => $linea->unidad,
                'cantidad' => (float) $linea->cantidad,
                'cantidad_pendiente' => $linea->presupuestoLinea?->cantidadPendiente() + (float) $linea->cantidad,
                'precio_unitario' => (float) $linea->precio_unitario,
                'descuento_porcentaje' => $linea->descuento_porcentaje !== null ? (float) $linea->descuento_porcentaje : null,
                'tipo_impositivo' => (float) $linea->tipo_impositivo,
            ])->values(),
        ]);
    }

    public function update(UpdateAlbaranRequest $request, string $albaran): RedirectResponse|JsonResponse
    {
        $albaran = Albaran::findOrFail($albaran);

        if (! $albaran->estado->esEditable()) {
            if ($request->wantsJson()) {
                return response()->json(['message' => 'Solo se pueden editar albaranes en borrador.'], 422);
            }

            abort(403, 'Solo se pueden editar albaranes en borrador.');
        }

        try {
            $this->registro->guardar($request->validated(), $albaran);
        } catch (CantidadAlbaranExcedeLoPendienteException $e) {
            if ($request->wantsJson()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        $this->registradorActividad->registrar(
            auth()->user(),
            AccionLogActividad::Modificacion,
            EntidadLogActividad::Albaran,
            $albaran->id,
            "Modificó el albarán {$albaran->numero}",
        );

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Albarán actualizado correctamente.']);
        }

        return redirect()->route('albaranes.show', $albaran)->with('success', 'Albarán actualizado correctamente.');
    }

    public function destroy(Request $request, string $albaran): RedirectResponse|JsonResponse
    {
        $albaran = Albaran::findOrFail($albaran);

        if ($albaran->estado !== EstadoAlbaran::Borrador) {
            abort(403, 'Solo se pueden eliminar albaranes en borrador.');
        }

        $numero = $albaran->numero;
        $albaran->delete();

        $this->registradorActividad->registrar(
            auth()->user(),
            AccionLogActividad::Baja,
            EntidadLogActividad::Albaran,
            null,
            "Eliminó el albarán {$numero}",
        );

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Albarán eliminado correctamente.']);
        }

        return redirect()->route('albaranes.index')->with('success', 'Albarán eliminado correctamente.');
    }

    public function estado(Request $request, string $albaran): RedirectResponse|JsonResponse
    {
        $albaran = Albaran::findOrFail($albaran);

        $datos = $request->validate([
            'estado' => ['required', 'string', 'in:entregado,anulado'],
        ]);

        try {
            $albaran = match ($datos['estado']) {
                'entregado' => $this->entregador->entregar($albaran),
                'anulado' => $this->anulador->anular($albaran),
            };
        } catch (AlbaranTransicionInvalidaException $e) {
            if ($request->wantsJson()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return redirect()->back()->with('error', $e->getMessage());
        }

        $this->registradorActividad->registrar(
            auth()->user(),
            AccionLogActividad::Modificacion,
            EntidadLogActividad::Albaran,
            $albaran->id,
            "Cambió el albarán {$albaran->numero} a estado {$datos['estado']}",
        );

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Estado actualizado correctamente.',
                'estado' => $albaran->estado->value,
                'estado_label' => $albaran->estado->label(),
            ]);
        }

        return redirect()->route('albaranes.show', $albaran)->with('success', 'Estado actualizado correctamente.');
    }

    public function convertir(Request $request): RedirectResponse|JsonResponse
    {
        $datos = $request->validate([
            'albaran_ids' => ['required', 'array', 'min:1'],
            'albaran_ids.*' => ['integer'],
        ]);

        $albaranes = Albaran::whereIn('id', $datos['albaran_ids'])->get();

        try {
            $factura = $this->conversor->convertir($albaranes);
        } catch (ConversionAlbaranesException $e) {
            if ($request->wantsJson()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return redirect()->back()->with('error', $e->getMessage());
        }

        $this->registradorActividad->registrar(
            auth()->user(),
            AccionLogActividad::Modificacion,
            EntidadLogActividad::Albaran,
            null,
            'Convirtió '.count($datos['albaran_ids'])." albarán(es) en la factura borrador #{$factura->id}",
        );

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Albaranes convertidos en factura borrador.',
                'redirect_url' => route('facturas.edit', $factura),
            ]);
        }

        return redirect()->route('facturas.edit', $factura)->with('success', 'Albaranes convertidos en factura borrador.');
    }
}
