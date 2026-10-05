<?php

namespace App\Http\Controllers\Pos;

use App\Exceptions\CuentaVersionDesfasadaException;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Pos\Concerns\RespondeConCuenta;
use App\Models\PosPrecuenta;
use App\Services\PrecuentaCuenta;
use App\Traduccion\Bilingue;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Precuentas del POS de hostelería (feature 049): documento **no fiscal** que se entrega a la mesa
 * antes de cobrar. Emitirla no crea factura, ni consume numeración, ni registra en Verifactu.
 *
 * Como el resto del módulo, **nada se resuelve por route binding implícito** (no pasa por el
 * `TenantScope`): cuenta y precuenta se buscan a mano bajo el scope del tenant.
 */
class PrecuentaController extends Controller
{
    use RespondeConCuenta;

    public function __construct(private readonly PrecuentaCuenta $precuentas) {}

    public function store(Request $request, string $cuenta): JsonResponse
    {
        $datos = $request->validate(['version' => ['required', 'integer']]);
        $modelo = $this->resolverCuenta($cuenta);

        if (! $modelo->estaAbierta()) {
            return response()->json(['message' => __('Esta cuenta ya no está abierta.')], 422);
        }

        if ($conflicto = $this->conflictoDeVersion($modelo, (int) $datos['version'])) {
            return $conflicto;
        }

        try {
            $precuenta = $this->precuentas->emitir($modelo, (int) $datos['version'], $request->user()?->id);
        } catch (CuentaVersionDesfasadaException) {
            // Otro dispositivo guardó entre la comprobación de arriba y el bloqueo: mismo 409.
            return $this->conflictoDeVersion($this->resolverCuenta($cuenta), -1);
        }

        return response()->json([
            'precuenta' => $this->precuentas->datosEmision($precuenta),
            'cuenta' => $this->payload($this->resolverCuenta($cuenta)),
        ], 201);
    }

    public function pdf(string $precuenta): Response
    {
        $modelo = PosPrecuenta::query()->with('usuario', 'tenant')->findOrFail($precuenta);

        // Rollo de 80 mm (≈ 226.77 pt), igual que el ticket. Alto según líneas: DomPDF recorta el
        // sobrante en blanco.
        // Fuente con caracteres chinos si sale bilingüe o algún dato los tiene (feature 050).
        $fuenteCjk = Bilingue::fuenteCjkPrecuenta($modelo);
        $alto = ($fuenteCjk ? 460 : 360) + (count($modelo->lineas) * 30);

        if ($fuenteCjk) {
            Bilingue::prepararCarpetaFuentes();
        }

        return Pdf::loadView('pos.precuenta-80mm', ['precuenta' => $modelo, 'fuenteCjk' => $fuenteCjk])
            ->setPaper([0, 0, 226.77, $alto])
            ->setOption('isFontSubsettingEnabled', $fuenteCjk)
            ->stream('precuenta-'.$modelo->id.'.pdf');
    }
}
