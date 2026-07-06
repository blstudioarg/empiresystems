<?php

namespace App\Http\Controllers;

use App\Exceptions\CertificadoInvalidoException;
use App\Exceptions\EmailNoConfiguradoException;
use App\Exceptions\FacturaeNoGenerableException;
use App\Models\Factura;
use App\Models\FacturaEvento;
use App\Services\EnvioFacturae;
use App\Services\GeneradorFacturae;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class FacturaeController extends Controller
{
    public function __construct(
        private readonly GeneradorFacturae $generador,
        private readonly EnvioFacturae $envio,
    ) {}

    /**
     * Genera (si no existe) y descarga el Facturae firmado. Resuelve la factura acotada por el
     * tenant activo vía `TenantScope` (findOrFail explícito, sin binding implícito).
     */
    public function descargar(Request $request, string $factura): Response|RedirectResponse|JsonResponse
    {
        $factura = Factura::findOrFail($factura);

        try {
            $resultado = $this->generador->generarYConservar($factura);
        } catch (FacturaeNoGenerableException|CertificadoInvalidoException $e) {
            if ($request->wantsJson()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return redirect()->back()->with('error', $e->getMessage());
        }

        if ($resultado['regenerado']) {
            FacturaEvento::create([
                'tenant_id' => $factura->tenant_id,
                'factura_id' => $factura->id,
                'tipo_evento' => 'facturae_generado',
                'detalle' => ['ruta' => $resultado['ruta']],
                'ocurrido_at' => now(),
            ]);
        }

        $nombre = ($factura->numero_completo ?? 'factura-'.$factura->id).'.xsig';

        return response($resultado['xml'], 200, [
            'Content-Type' => 'application/xml',
            'Content-Disposition' => "attachment; filename=\"{$nombre}\"",
        ]);
    }

    public function generarYEnviar(Request $request, string $factura): RedirectResponse|JsonResponse
    {
        $factura = Factura::findOrFail($factura);

        $destinatario = $request->filled('destinatario') ? $request->string('destinatario')->toString() : null;

        try {
            $resultado = $this->envio->generarYEnviar($factura, $destinatario);
        } catch (FacturaeNoGenerableException|CertificadoInvalidoException $e) {
            if ($request->wantsJson()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return redirect()->back()->with('error', $e->getMessage());
        }

        $mensaje = $resultado['aviso'] ?? 'Facturae generado y enviado correctamente.';
        $tipoFlash = $resultado['aviso'] ? 'warning' : 'success';

        if ($request->wantsJson()) {
            return response()->json(['message' => $mensaje, 'tipo' => $tipoFlash]);
        }

        return redirect()->back()->with($tipoFlash, $mensaje);
    }

    public function reenviar(Request $request, string $factura): RedirectResponse|JsonResponse
    {
        $factura = Factura::findOrFail($factura);

        $request->validate(['destinatario' => ['required', 'email']]);

        try {
            $this->envio->reenviar($factura, $request->string('destinatario')->toString());
        } catch (FacturaeNoGenerableException|EmailNoConfiguradoException $e) {
            if ($request->wantsJson()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return redirect()->back()->with('error', $e->getMessage());
        }

        $mensaje = 'Facturae reenviado correctamente.';

        if ($request->wantsJson()) {
            return response()->json(['message' => $mensaje]);
        }

        return redirect()->back()->with('success', $mensaje);
    }
}
