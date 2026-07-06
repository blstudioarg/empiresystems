<?php

namespace App\Http\Controllers;

use App\Enums\EstadoCompra;
use App\Exceptions\CompraNoModificableException;
use App\Http\Requests\StoreCompraRequest;
use App\Http\Requests\UpdateCompraRequest;
use App\Models\Articulo;
use App\Models\Compra;
use App\Models\Proveedor;
use App\Services\RegistroCompra;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class CompraController extends Controller
{
    public function __construct(private readonly RegistroCompra $registroCompra) {}

    public function index(Request $request): View|JsonResponse
    {
        if ($request->wantsJson()) {
            $compras = Compra::with('proveedor')
                ->when($request->filled('estado_b2b'), fn ($query) => $query->where('estado_b2b', $request->string('estado_b2b')->toString()))
                ->orderByDesc('fecha')
                ->get();

            return response()->json([
                'data' => $compras->map(fn (Compra $compra) => [
                    'id' => $compra->id,
                    'proveedor' => $compra->proveedor->razon_social ?: $compra->proveedor->nombre,
                    'numero_documento' => $compra->numero_documento,
                    'fecha' => $compra->fecha->toDateString(),
                    'estado' => $compra->estado->value,
                    'origen' => $compra->origen->value,
                    'estado_b2b' => $compra->estado_b2b?->value,
                    'total' => number_format((float) $compra->total, 2, '.', ''),
                    'show_url' => route('compras.show', $compra),
                ])->values(),
                'totales' => [
                    'total' => $compras->count(),
                    'confirmadas' => $compras->where('estado', EstadoCompra::Confirmada)->count(),
                    'importe_total' => number_format((float) $compras->where('estado', EstadoCompra::Confirmada)->sum('total'), 2, '.', ''),
                ],
            ]);
        }

        return view('compras.index');
    }

    public function create(): View
    {
        return view('compras.create', [
            'compra' => null,
            'proveedores' => Proveedor::orderBy('nombre')->get(),
            'articulos' => Articulo::orderBy('nombre')->get(),
            'lineasIniciales' => [],
        ]);
    }

    public function store(StoreCompraRequest $request): RedirectResponse|JsonResponse
    {
        $compra = $this->guardar($request->validated());

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Compra creada correctamente.', 'id' => $compra->id], 201);
        }

        return redirect()->route('compras.show', $compra)->with('success', 'Compra creada correctamente.');
    }

    public function show(Request $request, string $compra): View|JsonResponse
    {
        $compra = Compra::with(['lineas.articulo', 'proveedor', 'movimientos'])->findOrFail($compra);

        if ($request->wantsJson()) {
            return response()->json($this->compraJson($compra));
        }

        return view('compras.show', ['compra' => $compra]);
    }

    public function edit(string $compra): View
    {
        $compra = Compra::with('lineas')->findOrFail($compra);

        if ($compra->estado !== EstadoCompra::Borrador) {
            abort(403, 'Solo se pueden editar compras en borrador.');
        }

        return view('compras.edit', [
            'compra' => $compra,
            'proveedores' => Proveedor::orderBy('nombre')->get(),
            'articulos' => Articulo::orderBy('nombre')->get(),
            'lineasIniciales' => $compra->lineas->map(fn ($linea) => [
                'articulo_id' => $linea->articulo_id,
                'concepto' => $linea->concepto,
                'unidad' => $linea->unidad,
                'cantidad' => (float) $linea->cantidad,
                'precio_unitario' => (float) $linea->precio_unitario,
                'tipo_impositivo' => (float) $linea->tipo_impositivo,
            ])->values(),
        ]);
    }

    public function update(UpdateCompraRequest $request, string $compra): RedirectResponse|JsonResponse
    {
        $compra = Compra::findOrFail($compra);

        if ($compra->estado !== EstadoCompra::Borrador) {
            abort(403, 'Solo se pueden editar compras en borrador.');
        }

        $this->guardar($request->validated(), $compra);

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Compra actualizada correctamente.']);
        }

        return redirect()->route('compras.show', $compra)->with('success', 'Compra actualizada correctamente.');
    }

    public function destroy(Request $request, string $compra): RedirectResponse|JsonResponse
    {
        $compra = Compra::findOrFail($compra);

        if ($compra->estado !== EstadoCompra::Borrador) {
            abort(403, 'Solo se pueden eliminar compras en borrador.');
        }

        $compra->delete();

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Compra eliminada correctamente.']);
        }

        return redirect()->route('compras.index')->with('success', 'Compra eliminada correctamente.');
    }

    public function confirmar(Request $request, string $compra): RedirectResponse|JsonResponse
    {
        $compra = Compra::findOrFail($compra);

        try {
            $compra = $this->registroCompra->confirmar($compra);
        } catch (CompraNoModificableException $e) {
            if ($request->wantsJson()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return redirect()->back()->with('error', $e->getMessage());
        }

        if ($request->wantsJson()) {
            return response()->json(array_merge(
                ['message' => 'Compra confirmada correctamente.'],
                $this->compraJson($compra->load(['lineas.articulo', 'movimientos']))
            ));
        }

        return redirect()->route('compras.show', $compra)->with('success', 'Compra confirmada correctamente.');
    }

    public function anular(Request $request, string $compra): RedirectResponse|JsonResponse
    {
        $compra = Compra::findOrFail($compra);

        try {
            $compra = $this->registroCompra->anular($compra);
        } catch (CompraNoModificableException $e) {
            if ($request->wantsJson()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return redirect()->back()->with('error', $e->getMessage());
        }

        if ($request->wantsJson()) {
            return response()->json(array_merge(
                ['message' => 'Compra anulada correctamente.'],
                $this->compraJson($compra->load(['lineas.articulo', 'movimientos']))
            ));
        }

        return redirect()->route('compras.show', $compra)->with('success', 'Compra anulada correctamente.');
    }

    /**
     * @return array<string, mixed>
     */
    private function compraJson(Compra $compra): array
    {
        return [
            'id' => $compra->id,
            'estado' => $compra->estado->value,
            'confirmar_url' => route('compras.confirmar', $compra),
            'anular_url' => route('compras.anular', $compra),
            'edit_url' => route('compras.edit', $compra),
            'movimientos' => $compra->movimientos->map(fn ($m) => [
                'tipo' => $m->tipo->value,
                'cantidad' => (float) $m->cantidad,
                'stock_resultante' => (float) $m->stock_resultante,
            ])->values(),
        ];
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function guardar(array $datos, ?Compra $compra = null): Compra
    {
        return DB::transaction(function () use ($datos, $compra) {
            $baseTotal = 0;
            $cuotaImpuestoTotal = 0;
            $lineasCalculadas = [];

            foreach ($datos['lineas'] as $orden => $linea) {
                $base = round((float) $linea['cantidad'] * (float) $linea['precio_unitario'], 2);
                $cuota = round($base * (float) $linea['tipo_impositivo'] / 100, 2);

                $baseTotal += $base;
                $cuotaImpuestoTotal += $cuota;

                $lineasCalculadas[] = [
                    'articulo_id' => $linea['articulo_id'] ?? null,
                    'concepto' => $linea['concepto'],
                    'unidad' => $linea['unidad'] ?? null,
                    'cantidad' => $linea['cantidad'],
                    'precio_unitario' => $linea['precio_unitario'],
                    'base' => $base,
                    'tipo_impositivo' => $linea['tipo_impositivo'],
                    'cuota_impuesto' => $cuota,
                    'orden' => $orden,
                ];
            }

            $cabecera = [
                'proveedor_id' => $datos['proveedor_id'],
                'numero_documento' => $datos['numero_documento'] ?? null,
                'fecha' => $datos['fecha'],
                'notas' => $datos['notas'] ?? null,
                'base_total' => round($baseTotal, 2),
                'cuota_impuesto_total' => round($cuotaImpuestoTotal, 2),
                'total' => round($baseTotal + $cuotaImpuestoTotal, 2),
            ];

            if ($compra) {
                $compra->update($cabecera);
                $compra->lineas()->delete();
            } else {
                $compra = Compra::create($cabecera + ['estado' => EstadoCompra::Borrador]);
            }

            foreach ($lineasCalculadas as $linea) {
                $compra->lineas()->create($linea);
            }

            return $compra->refresh();
        });
    }
}
