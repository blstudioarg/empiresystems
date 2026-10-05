<?php

namespace App\Http\Controllers\Pos\Concerns;

use App\Models\PosCuenta;
use App\Models\PosCuentaLinea;
use App\Services\PrecuentaCuenta;
use App\Support\ConfigPos;
use Illuminate\Http\JsonResponse;

/**
 * Resolución, payload y conflicto de versión de una cuenta abierta, compartidos por los
 * controladores que devuelven la cuenta al TPV (cuentas, feature 038; precuentas, feature 049).
 * Un único payload evita que dos endpoints describan la misma cuenta de forma distinta.
 */
trait RespondeConCuenta
{
    private function resolverCuenta(string $id): PosCuenta
    {
        // Resolución manual bajo TenantScope: `findOrFail` sobre el modelo (no binding implícito).
        return PosCuenta::query()->with('lineas.opciones', 'mesa.zona')->findOrFail($id);
    }

    /**
     * Bloqueo optimista (FR-024): si la versión que trae el cliente no es la de base de datos,
     * otro dispositivo tocó la cuenta. Se responde 409 con el estado actual y **nunca** se
     * reintenta en silencio, que sería exactamente pisar los cambios del otro camarero.
     */
    private function conflictoDeVersion(PosCuenta $cuenta, int $version): ?JsonResponse
    {
        if ($version === (int) $cuenta->version) {
            return null;
        }

        return response()->json([
            'message' => 'Otro dispositivo modificó esta cuenta mientras la tenías abierta. Se recargó con los datos actuales.',
            'cuenta' => $this->payload($cuenta),
        ], 409);
    }

    /** @return array<string, mixed> */
    private function payload(PosCuenta $cuenta): array
    {
        $cuenta->loadMissing('lineas.opciones', 'mesa.zona', 'ultimaPrecuenta');
        $tenantId = (int) $cuenta->tenant_id;

        return [
            'id' => $cuenta->id,
            'estado' => $cuenta->estado,
            'version' => (int) $cuenta->version,
            'mesa_id' => $cuenta->mesa_id,
            'mesa_nombre' => $cuenta->mesa?->nombre,
            'zona_nombre' => $cuenta->mesa?->zona?->nombre,
            'zona_suplemento' => ConfigPos::suplementoZonaActivo($tenantId)
                ? number_format((float) ($cuenta->mesa?->zona?->suplemento_porcentaje ?? 0), 2, '.', '')
                : '0.00',
            'comensales' => $cuenta->comensales,
            'notas' => $cuenta->notas,
            'receptor' => $cuenta->cliente_nif ? [
                'cliente_id' => $cuenta->cliente_id,
                'cliente_nif' => $cuenta->cliente_nif,
                'cliente_nombre' => $cuenta->cliente_nombre,
                'cliente_razon_social' => $cuenta->cliente_razon_social,
                'cliente_direccion' => $cuenta->cliente_direccion,
            ] : null,
            'pendiente' => number_format($cuenta->pendiente(), 2, '.', ''),
            // Precuenta (feature 049): estado DERIVADO por huella, nunca guardado en la cuenta.
            'precuenta' => app(PrecuentaCuenta::class)->resumen($cuenta, $cuenta->ultimaPrecuenta),
            'lineas' => $cuenta->lineas->map(fn (PosCuentaLinea $linea) => [
                'id' => $linea->id,
                'articulo_id' => $linea->articulo_id,
                'concepto' => $linea->concepto,
                'unidad' => $linea->unidad,
                'cantidad' => (float) $linea->cantidad,
                'cantidad_saldada' => (float) $linea->cantidad_saldada,
                'cantidad_pendiente' => $linea->cantidadPendiente(),
                'precio_unitario' => (float) $linea->precio_unitario,
                'suplemento_opciones' => (float) $linea->suplemento_opciones,
                'precio_efectivo' => $linea->precioEfectivo(),
                'tipo_impositivo' => (float) $linea->tipo_impositivo,
                'importe_pendiente' => $linea->brutoPendiente(),
                'opciones' => $linea->opciones->map(fn ($o) => [
                    'opcion_id' => $o->opcion_id,
                    'nombre' => $o->nombre,
                    'precio' => (float) $o->precio,
                ])->values(),
            ])->values(),
        ];
    }
}
