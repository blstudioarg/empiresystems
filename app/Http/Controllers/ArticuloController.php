<?php

namespace App\Http\Controllers;

use App\Enums\AccionLogActividad;
use App\Enums\EntidadLogActividad;
use App\Enums\TipoArticulo;
use App\Http\Requests\StoreArticuloRequest;
use App\Http\Requests\UpdateArticuloRequest;
use App\Models\Articulo;
use App\Services\RegistradorActividad;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class ArticuloController extends Controller
{
    public function __construct(
        private readonly RegistradorActividad $registradorActividad,
    ) {}

    public function index(Request $request): View|JsonResponse
    {
        if ($request->wantsJson()) {
            $articulos = Articulo::with('categoria:id,nombre')->orderBy('nombre')->get();

            return response()->json([
                'data' => $articulos->map(fn (Articulo $articulo) => [
                    'id' => $articulo->id,
                    'tipo' => $articulo->tipo->value,
                    'tipo_label' => $articulo->tipo === TipoArticulo::Producto ? 'Producto' : 'Servicio',
                    'sku' => $articulo->sku,
                    'nombre' => $articulo->nombre,
                    'descripcion' => $articulo->descripcion,
                    'imagen_url' => $articulo->imagenUrl(),
                    'unidad' => $articulo->unidad,
                    'categoria_id' => $articulo->categoria_id,
                    'categoria_nombre' => $articulo->categoria?->nombre,
                    'precio' => $articulo->precio,
                    'tipo_impositivo' => $articulo->tipo_impositivo,
                    'gestion_stock' => $articulo->gestion_stock,
                    'stock_actual' => $articulo->stock_actual,
                    'stock_minimo' => $articulo->stock_minimo,
                    'aplica_recargo_equivalencia' => $articulo->aplica_recargo_equivalencia,
                    'activo' => $articulo->activo,
                    'update_url' => route('articulos.update', $articulo),
                    'delete_url' => route('articulos.destroy', $articulo),
                ])->values(),
                'totales' => [
                    'total' => $articulos->count(),
                    'productos' => $articulos->where('tipo', TipoArticulo::Producto)->count(),
                    'servicios' => $articulos->where('tipo', TipoArticulo::Servicio)->count(),
                ],
            ]);
        }

        return view('articulos.index');
    }

    public function store(StoreArticuloRequest $request): RedirectResponse|JsonResponse
    {
        $datos = $this->normalizarStock($request->validated());

        if ($request->hasFile('imagen')) {
            $datos['imagen_path'] = $request->file('imagen')->store('articulos/'.tenant('id'), 'public');
        }

        $articulo = Articulo::create($datos);

        $this->registradorActividad->registrar(
            auth()->user(),
            AccionLogActividad::Alta,
            EntidadLogActividad::Articulo,
            $articulo->id,
            "Creó el artículo {$articulo->nombre}",
        );

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Artículo creado correctamente.'], 201);
        }

        return redirect()->route('articulos.index')->with('success', 'Artículo creado correctamente.');
    }

    public function update(UpdateArticuloRequest $request, string $articulo): RedirectResponse|JsonResponse
    {
        // No se usa binding implícito de ruta (mismo motivo que ClienteController@update):
        // se resuelve manualmente aquí, una vez el TenantScope ya está garantizado.
        $articulo = Articulo::findOrFail($articulo);

        $datos = $this->normalizarStock($request->validated());

        if ($request->hasFile('imagen')) {
            if ($articulo->imagen_path) {
                Storage::disk('public')->delete($articulo->imagen_path);
            }

            $datos['imagen_path'] = $request->file('imagen')->store('articulos/'.tenant('id'), 'public');
        } elseif ($request->boolean('quitar_imagen')) {
            if ($articulo->imagen_path) {
                Storage::disk('public')->delete($articulo->imagen_path);
            }

            $datos['imagen_path'] = null;
        }

        $articulo->update($datos);

        $this->registradorActividad->registrar(
            auth()->user(),
            AccionLogActividad::Modificacion,
            EntidadLogActividad::Articulo,
            $articulo->id,
            "Modificó el artículo {$articulo->nombre}",
        );

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Artículo actualizado correctamente.']);
        }

        return redirect()->route('articulos.index')->with('success', 'Artículo actualizado correctamente.');
    }

    public function destroy(Request $request, string $articulo): RedirectResponse|JsonResponse
    {
        $articulo = Articulo::findOrFail($articulo);

        $articulo->delete();

        $this->registradorActividad->registrar(
            auth()->user(),
            AccionLogActividad::Baja,
            EntidadLogActividad::Articulo,
            $articulo->id,
            "Eliminó el artículo {$articulo->nombre}",
        );

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Artículo eliminado correctamente.']);
        }

        return redirect()->route('articulos.index')->with('success', 'Artículo eliminado correctamente.');
    }

    /**
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     */
    private function normalizarStock(array $datos): array
    {
        $esProducto = ($datos['tipo'] ?? null) === TipoArticulo::Producto->value;

        if (! $esProducto || empty($datos['gestion_stock'])) {
            $datos['gestion_stock'] = false;
            $datos['stock_actual'] = null;
            $datos['stock_minimo'] = null;
        }

        return $datos;
    }
}
