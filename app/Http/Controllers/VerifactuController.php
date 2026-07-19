<?php

namespace App\Http\Controllers;

use App\Jobs\RemitirRegistroVerifactu;
use App\Models\Factura;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Reintento manual del envío Verifactu de una factura en error (FR-019, contracts/aeat-webservice.md).
 * Reenvía el registro ya sellado: nunca recalcula huella ni crea un eslabón nuevo (FR-013).
 */
class VerifactuController extends Controller
{
    public function reintentar(Request $request, string $factura): RedirectResponse|JsonResponse
    {
        $factura = Factura::findOrFail($factura);

        if (! $factura->verifactuReintentable()) {
            $mensaje = 'Esta factura no tiene un envío Verifactu en error para reintentar.';

            return $request->wantsJson()
                ? response()->json(['message' => $mensaje], 422)
                : redirect()->back()->with('error', $mensaje);
        }

        RemitirRegistroVerifactu::dispatch($factura->id);

        $mensaje = 'Reintento de envío a la AEAT encolado.';

        return $request->wantsJson()
            ? response()->json(['message' => $mensaje])
            : redirect()->back()->with('success', $mensaje);
    }
}
