<?php

namespace App\Services;

use App\Enums\EstadoFactura;
use App\Exceptions\PagoInvalidoException;
use App\Models\Factura;
use App\Models\Pago;
use Illuminate\Support\Facades\DB;

class RegistroPagos
{
    /**
     * @param  array<string, mixed>  $datos
     */
    public function registrar(Factura $factura, array $datos): Pago
    {
        if ($factura->estado !== EstadoFactura::Emitida) {
            throw new PagoInvalidoException('Solo se pueden registrar pagos de facturas emitidas.');
        }

        return DB::transaction(function () use ($factura, $datos) {
            $importeCentimos = (int) round(((float) $datos['importe']) * 100);
            $cobradoCentimos = (int) round($factura->montoCobrado() * 100);
            $totalCentimos = (int) round((float) $factura->total * 100);

            if ($cobradoCentimos + $importeCentimos > $totalCentimos) {
                throw new PagoInvalidoException('El pago excede el saldo pendiente de la factura.');
            }

            return Pago::create([
                'tenant_id' => $factura->tenant_id,
                'factura_id' => $factura->id,
                'fecha' => $datos['fecha'],
                'importe' => $datos['importe'],
                'metodo' => $datos['metodo'],
                'referencia' => $datos['referencia'] ?? null,
            ]);
        });
    }

    public function anular(Pago $pago): Pago
    {
        if ($pago->estaAnulado()) {
            throw new PagoInvalidoException('El pago ya estaba anulado.');
        }

        return DB::transaction(function () use ($pago) {
            $pago->anulado_at = now();
            $pago->save();

            return $pago;
        });
    }
}
