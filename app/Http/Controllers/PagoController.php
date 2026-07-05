<?php

namespace App\Http\Controllers;

use App\Exceptions\PagoInvalidoException;
use App\Http\Requests\StorePagoRequest;
use App\Models\Factura;
use App\Models\Pago;
use App\Services\RegistroPagos;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PagoController extends Controller
{
    public function __construct(private readonly RegistroPagos $registroPagos) {}

    public function index(string $factura): JsonResponse
    {
        $factura = Factura::findOrFail($factura);

        $pagos = $factura->pagos()->orderByDesc('fecha')->orderByDesc('id')->get();

        return response()->json([
            'saldo_pendiente' => number_format($factura->saldoPendiente(), 2, '.', ''),
            'estado_cobro' => $factura->estadoCobro()->value,
            'data' => $pagos->map(fn (Pago $pago) => [
                'id' => $pago->id,
                'fecha' => $pago->fecha->toDateString(),
                'importe' => number_format((float) $pago->importe, 2, '.', ''),
                'metodo' => $pago->metodo->value,
                'referencia' => $pago->referencia,
                'vigente' => ! $pago->estaAnulado(),
                'anular_url' => ! $pago->estaAnulado() ? route('pagos.anular', $pago) : null,
            ])->values(),
        ]);
    }

    public function store(StorePagoRequest $request, string $factura): RedirectResponse|JsonResponse
    {
        $factura = Factura::findOrFail($factura);

        try {
            $pago = $this->registroPagos->registrar($factura, $request->validated());
        } catch (PagoInvalidoException $e) {
            if ($request->wantsJson()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return redirect()->back()->with('error', $e->getMessage());
        }

        $factura->refresh();

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Pago registrado correctamente.',
                'id' => $pago->id,
                'saldo_pendiente' => number_format($factura->saldoPendiente(), 2, '.', ''),
                'estado_cobro' => $factura->estadoCobro()->value,
            ], 201);
        }

        return redirect()->back()->with('success', 'Pago registrado correctamente.');
    }

    public function anular(Request $request, string $pago): RedirectResponse|JsonResponse
    {
        $pago = Pago::findOrFail($pago);

        try {
            $pago = $this->registroPagos->anular($pago);
        } catch (PagoInvalidoException $e) {
            if ($request->wantsJson()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return redirect()->back()->with('error', $e->getMessage());
        }

        $factura = $pago->factura()->first();

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Pago anulado.',
                'saldo_pendiente' => number_format($factura->saldoPendiente(), 2, '.', ''),
                'estado_cobro' => $factura->estadoCobro()->value,
            ]);
        }

        return redirect()->back()->with('success', 'Pago anulado.');
    }
}
