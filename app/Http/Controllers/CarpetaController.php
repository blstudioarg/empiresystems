<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCarpetaRequest;
use App\Http\Requests\UpdateCarpetaRequest;
use App\Models\Carpeta;
use App\Services\AlmacenArchivos;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CarpetaController extends Controller
{
    public function store(StoreCarpetaRequest $request): RedirectResponse|JsonResponse
    {
        $carpeta = Carpeta::create($request->validated());

        if ($request->wantsJson()) {
            return response()->json(['data' => $this->carpetaPayload($carpeta)], 201);
        }

        return redirect()->route('archivos.index')->with('success', 'Carpeta creada correctamente.');
    }

    public function update(UpdateCarpetaRequest $request, string $carpeta): RedirectResponse|JsonResponse
    {
        $carpeta = Carpeta::findOrFail($carpeta);
        $carpeta->update($request->validated());

        if ($request->wantsJson()) {
            return response()->json(['data' => $this->carpetaPayload($carpeta->fresh())]);
        }

        return redirect()->route('archivos.index')->with('success', 'Carpeta actualizada correctamente.');
    }

    public function destroy(Request $request, string $carpeta, AlmacenArchivos $almacen): RedirectResponse|JsonResponse
    {
        $carpeta = Carpeta::findOrFail($carpeta);

        $totalBorrados = DB::transaction(fn () => $this->borrarEnCascada($carpeta, $almacen));

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Carpeta eliminada correctamente.',
                'elementos_borrados' => $totalBorrados,
            ]);
        }

        return redirect()->route('archivos.index')->with('success', 'Carpeta eliminada correctamente.');
    }

    /**
     * Recorre el subárbol (subcarpetas + archivos) borrando registros (soft delete) y ficheros
     * físicos, en la misma transacción que la carpeta raíz del borrado (D4).
     */
    private function borrarEnCascada(Carpeta $carpeta, AlmacenArchivos $almacen): int
    {
        $total = 1;

        foreach ($carpeta->archivos as $archivo) {
            $almacen->borrar($archivo->ruta);
            $archivo->delete();
            $total++;
        }

        foreach ($carpeta->subcarpetas as $subcarpeta) {
            $total += $this->borrarEnCascada($subcarpeta, $almacen);
        }

        $carpeta->delete();

        return $total;
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
