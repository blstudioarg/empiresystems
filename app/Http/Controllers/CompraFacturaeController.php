<?php

namespace App\Http\Controllers;

use App\Enums\EstadoB2b;
use App\Enums\OrigenCompra;
use App\Exceptions\FacturaeImportacionException;
use App\Models\Compra;
use App\Services\ImportadorFacturae;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class CompraFacturaeController extends Controller
{
    public function __construct(private readonly ImportadorFacturae $importador) {}

    public function importar(Request $request): RedirectResponse|JsonResponse
    {
        $datos = $request->validate([
            'archivo' => ['required', 'file'],
        ]);

        try {
            $resultado = $this->importador->importar($datos['archivo'], tenant()->getTenantKey());
        } catch (FacturaeImportacionException $e) {
            if ($request->wantsJson()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return redirect()->back()->with('error', $e->getMessage());
        }

        if ($resultado['duplicado']) {
            $mensaje = 'Ya existe una compra con el mismo proveedor, número y fecha: posible duplicado, no se ha creado.';

            if ($request->wantsJson()) {
                return response()->json(['message' => $mensaje], 409);
            }

            return redirect()->route('compras.index')->with('warning', $mensaje);
        }

        $compra = $resultado['compra'];
        $mensaje = 'Compra importada correctamente desde el Facturae.';

        if (! $resultado['firma_verificable']) {
            $mensaje .= ' Aviso: la firma del documento no se ha podido verificar.';
        }

        if ($request->wantsJson()) {
            return response()->json(['message' => $mensaje, 'id' => $compra->id], 201);
        }

        return redirect()->route('compras.show', $compra)
            ->with($resultado['firma_verificable'] ? 'success' : 'warning', $mensaje);
    }

    public function descargar(string $compra): Response|RedirectResponse
    {
        $compra = Compra::findOrFail($compra);

        if ($compra->origen !== OrigenCompra::Facturae || ! $compra->archivo_recibido_path) {
            abort(404, 'Esta compra no tiene un Facturae recibido asociado.');
        }

        if (! Storage::disk('documentos')->exists($compra->archivo_recibido_path)) {
            abort(404, 'El archivo recibido ya no está disponible.');
        }

        return response(
            Storage::disk('documentos')->get($compra->archivo_recibido_path),
            200,
            [
                'Content-Type' => 'application/xml',
                'Content-Disposition' => 'attachment; filename="'.($compra->numero_documento ?? 'compra-'.$compra->id).'.xml"',
            ]
        );
    }

    public function cambiarEstadoB2b(Request $request, string $compra): RedirectResponse|JsonResponse
    {
        $compra = Compra::findOrFail($compra);

        if ($compra->origen !== OrigenCompra::Facturae) {
            $mensaje = 'Solo las compras recibidas por Facturae tienen estado de ciclo B2B.';

            if ($request->wantsJson()) {
                return response()->json(['message' => $mensaje], 422);
            }

            return redirect()->back()->with('error', $mensaje);
        }

        $datos = $request->validate([
            'estado_b2b' => ['required', 'string', 'in:'.implode(',', array_column(EstadoB2b::cases(), 'value'))],
        ]);

        $compra->update([
            'estado_b2b' => $datos['estado_b2b'],
            'estado_b2b_fecha' => now(),
        ]);

        $mensaje = 'Estado de la compra actualizado correctamente.';

        if ($request->wantsJson()) {
            return response()->json(['message' => $mensaje]);
        }

        return redirect()->back()->with('success', $mensaje);
    }
}
