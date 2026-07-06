<?php

namespace App\Http\Controllers;

use App\Enums\OrigenMovimientoStock;
use App\Enums\TipoArticulo;
use App\Enums\TipoMovimientoStock;
use App\Exceptions\MovimientoStockInvalidoException;
use App\Http\Requests\StoreAjusteStockRequest;
use App\Models\Articulo;
use App\Models\MovimientoStock;
use App\Services\RegistroMovimientoStock;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MovimientoStockController extends Controller
{
    public function __construct(private readonly RegistroMovimientoStock $registro) {}

    public function index(Request $request): View|JsonResponse
    {
        if ($request->wantsJson()) {
            return response()->json($this->datosJson());
        }

        return view('stock.index');
    }

    public function show(Request $request, string $articulo): View|JsonResponse
    {
        $articulo = Articulo::findOrFail($articulo);

        if ($request->wantsJson()) {
            return response()->json($this->datosJson($articulo));
        }

        return view('stock.index', ['articuloFiltrado' => $articulo]);
    }

    public function ajuste(StoreAjusteStockRequest $request): RedirectResponse|JsonResponse
    {
        $datos = $request->validated();

        $articulo = Articulo::findOrFail($datos['articulo_id']);

        try {
            $this->registro->registrar(
                articulo: $articulo,
                tipo: TipoMovimientoStock::from($datos['tipo']),
                cantidad: (float) $datos['cantidad'],
                origen: OrigenMovimientoStock::AjusteManual,
                motivo: $datos['motivo'],
            );
        } catch (MovimientoStockInvalidoException $e) {
            if ($request->wantsJson()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return redirect()->back()->with('error', $e->getMessage());
        }

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Ajuste de stock registrado correctamente.'], 201);
        }

        return redirect()->route('stock.index')->with('success', 'Ajuste de stock registrado correctamente.');
    }

    /**
     * @return array<string, mixed>
     */
    private function datosJson(?Articulo $articuloFiltrado = null): array
    {
        $movimientos = $articuloFiltrado
            ? $articuloFiltrado->movimientos()->with('articulo')->orderByDesc('ocurrido_at')->get()
            : MovimientoStock::with('articulo')->orderByDesc('ocurrido_at')->get();

        $articulosGestionados = Articulo::where('tipo', TipoArticulo::Producto)->where('gestion_stock', true)->count();
        $alertas = Articulo::bajoMinimo()->count();

        return [
            'totales' => [
                'articulos_gestionados' => $articulosGestionados,
                'movimientos' => $movimientos->count(),
                'alertas' => $alertas,
            ],
            'data' => $movimientos->map(fn (MovimientoStock $m) => [
                'id' => $m->id,
                'articulo' => $m->articulo->nombre,
                'tipo' => $m->tipo->value,
                'cantidad' => (float) $m->cantidad,
                'stock_resultante' => (float) $m->stock_resultante,
                'origen' => $m->origen->value,
                'motivo' => $m->motivo,
                'ocurrido_at' => $m->ocurrido_at->enZonaTenant()->toDateTimeString(),
            ])->values(),
            'alertas' => Articulo::bajoMinimo()->orderBy('nombre')->get()->map(fn (Articulo $a) => [
                'nombre' => $a->nombre,
                'stock_actual' => (float) $a->stock_actual,
                'stock_minimo' => (float) $a->stock_minimo,
            ])->values(),
            'articulos' => Articulo::where('tipo', TipoArticulo::Producto)
                ->where('gestion_stock', true)
                ->orderBy('nombre')
                ->get()
                ->map(fn (Articulo $a) => [
                    'id' => $a->id,
                    'nombre' => $a->nombre,
                    'stock_actual' => (float) $a->stock_actual,
                ])->values(),
        ];
    }
}
