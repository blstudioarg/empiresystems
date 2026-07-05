<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreArchivoRequest;
use App\Http\Requests\UpdateArchivoRequest;
use App\Models\Archivo;
use App\Models\Carpeta;
use App\Services\AlmacenArchivos;
use App\Support\ArchivosTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ArchivoController extends Controller
{
    public function index(Request $request): View|JsonResponse
    {
        $busqueda = trim((string) $request->query('q', ''));

        if ($busqueda !== '' && $request->wantsJson()) {
            return $this->buscar($busqueda);
        }

        $carpetaId = $request->integer('carpeta') ?: null;
        $carpetaActual = null;
        $breadcrumbs = [];

        if ($carpetaId) {
            $carpetaActual = Carpeta::findOrFail($carpetaId);

            $nodo = $carpetaActual;
            while ($nodo) {
                array_unshift($breadcrumbs, ['id' => $nodo->id, 'nombre' => $nodo->nombre]);
                $nodo = $nodo->padre;
            }
        }

        $subcarpetas = Carpeta::where('parent_id', $carpetaId)->orderBy('nombre')->get();
        $archivos = Archivo::with('subidoPor')->where('carpeta_id', $carpetaId)->orderBy('nombre')->get();
        $totales = $this->totales();

        if ($request->wantsJson()) {
            return response()->json([
                'carpeta_actual' => $carpetaActual?->id,
                'breadcrumbs' => $breadcrumbs,
                'carpetas' => $subcarpetas->map(fn (Carpeta $c) => $this->carpetaPayload($c))->values(),
                'data' => $archivos->map(fn (Archivo $a) => $this->archivoPayload($a))->values(),
                'totales' => $totales,
            ]);
        }

        return view('archivos.index', [
            'carpetaActual' => $carpetaActual,
            'breadcrumbs' => $breadcrumbs,
            'subcarpetas' => $subcarpetas,
            'archivos' => $archivos,
            'totales' => $totales,
            'limiteMb' => ArchivosTenant::limiteMb(tenant()->getTenantKey()),
            'extensionesPermitidas' => ArchivosTenant::EXTENSIONES_PERMITIDAS,
        ]);
    }

    /**
     * Búsqueda global por nombre (archivos y carpetas, en cualquier nivel del tenant), no acotada
     * a la carpeta actual. Limitada a 50 resultados por tipo: sin paginación en el MVP (Principio
     * V, volumen esperado bajo para documentos ligeros).
     */
    private function buscar(string $termino): JsonResponse
    {
        $carpetas = Carpeta::where('nombre', 'like', '%'.$termino.'%')
            ->orderBy('nombre')
            ->limit(50)
            ->get();

        $archivos = Archivo::with(['subidoPor', 'carpeta'])
            ->where('nombre', 'like', '%'.$termino.'%')
            ->orderBy('nombre')
            ->limit(50)
            ->get();

        return response()->json([
            'buscando' => true,
            'termino' => $termino,
            'carpeta_actual' => null,
            'breadcrumbs' => [],
            'carpetas' => $carpetas->map(fn (Carpeta $c) => [
                ...$this->carpetaPayload($c),
                'ruta' => $this->rutaDe($c->padre),
            ])->values(),
            'data' => $archivos->map(fn (Archivo $a) => [
                ...$this->archivoPayload($a),
                'ruta' => $this->rutaDe($a->carpeta),
            ])->values(),
            'totales' => $this->totales(),
        ]);
    }

    /**
     * Ruta legible (breadcrumb en texto) de una carpeta, para orientar los resultados de búsqueda
     * que pueden vivir en cualquier nivel del árbol. `null` (raíz) → 'Raíz'.
     */
    private function rutaDe(?Carpeta $carpeta): string
    {
        $partes = [];

        while ($carpeta) {
            array_unshift($partes, $carpeta->nombre);
            $carpeta = $carpeta->padre;
        }

        return $partes === [] ? 'Raíz' : implode(' / ', $partes);
    }

    /**
     * Métricas globales del espacio del tenant (no del nivel actual), para las cards informativas.
     *
     * @return array{archivos: int, carpetas: int, espacio_bytes: int}
     */
    private function totales(): array
    {
        return [
            'archivos' => Archivo::count(),
            'carpetas' => Carpeta::count(),
            'espacio_bytes' => (int) Archivo::sum('tamano'),
        ];
    }

    public function store(StoreArchivoRequest $request, AlmacenArchivos $almacen): RedirectResponse|JsonResponse
    {
        $tenantId = tenant()->getTenantKey();
        $datos = $request->validated();

        $guardado = $almacen->guardar($request->file('archivo'), $tenantId);

        try {
            $archivo = DB::transaction(function () use ($datos, $guardado) {
                return Archivo::create([
                    'carpeta_id' => $datos['carpeta_id'] ?? null,
                    'nombre' => $guardado['nombre_original'],
                    'nombre_original' => $guardado['nombre_original'],
                    'ruta' => $guardado['ruta'],
                    'mime' => $guardado['mime'],
                    'extension' => $guardado['extension'],
                    'tamano' => $guardado['tamano'],
                    'subido_por' => auth()->id(),
                ]);
            });
        } catch (\Throwable $e) {
            $almacen->borrar($guardado['ruta']);
            throw $e;
        }

        if ($request->wantsJson()) {
            return response()->json(['data' => $this->archivoPayload($archivo)], 201);
        }

        return redirect()->route('archivos.index')->with('success', 'Archivo subido correctamente.');
    }

    public function update(UpdateArchivoRequest $request, string $archivo): RedirectResponse|JsonResponse
    {
        $archivo = Archivo::findOrFail($archivo);
        $archivo->update($request->validated());

        if ($request->wantsJson()) {
            return response()->json(['data' => $this->archivoPayload($archivo->fresh('subidoPor'))]);
        }

        return redirect()->route('archivos.index')->with('success', 'Archivo actualizado correctamente.');
    }

    public function destroy(Request $request, string $archivo, AlmacenArchivos $almacen): RedirectResponse|JsonResponse
    {
        $archivo = Archivo::findOrFail($archivo);

        DB::transaction(function () use ($archivo, $almacen) {
            $archivo->delete();
            $almacen->borrar($archivo->ruta);
        });

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Archivo eliminado correctamente.']);
        }

        return redirect()->route('archivos.index')->with('success', 'Archivo eliminado correctamente.');
    }

    public function descargar(string $archivo): StreamedResponse
    {
        // Resolución manual acotada al tenant activo (nunca binding implícito), ver
        // memoria project-tenant-route-binding: el fichero es el punto de mayor riesgo de fuga.
        $archivo = Archivo::findOrFail($archivo);

        return Storage::disk('documentos')->download($archivo->ruta, $archivo->nombre);
    }

    public function preview(string $archivo): StreamedResponse
    {
        $archivo = Archivo::findOrFail($archivo);

        if (! ArchivosTenant::tienePreview($archivo->extension)) {
            abort(404);
        }

        return Storage::disk('documentos')->response($archivo->ruta, $archivo->nombre, [
            'Content-Type' => $archivo->mime,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function archivoPayload(Archivo $archivo): array
    {
        return [
            'id' => $archivo->id,
            'nombre' => $archivo->nombre,
            'extension' => $archivo->extension,
            'mime' => $archivo->mime,
            'tamano' => (int) $archivo->tamano,
            'carpeta_id' => $archivo->carpeta_id,
            'subido_por' => $archivo->subidoPor?->name,
            'creado_en' => $archivo->created_at?->toIso8601String(),
            'tiene_preview' => ArchivosTenant::tienePreview($archivo->extension),
            'descargar_url' => route('archivos.descargar', $archivo),
            'preview_url' => route('archivos.preview', $archivo),
            'update_url' => route('archivos.update', $archivo),
            'delete_url' => route('archivos.destroy', $archivo),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function carpetaPayload(Carpeta $carpeta): array
    {
        return [
            'id' => $carpeta->id,
            'nombre' => $carpeta->nombre,
            'parent_id' => $carpeta->parent_id,
            'update_url' => route('carpetas.update', $carpeta),
            'delete_url' => route('carpetas.destroy', $carpeta),
        ];
    }
}
