<?php

namespace App\Services;

use App\Enums\EstadoFactura;
use App\Exceptions\FacturaNoEmitibleException;
use App\Models\Factura;
use App\Models\FacturaEvento;
use App\Support\VencimientoFactura;
use Illuminate\Support\Facades\DB;

class EmisorFacturas
{
    public function __construct(private readonly NumeradorFacturas $numerador) {}

    public function emitir(Factura $factura): Factura
    {
        $this->validar($factura);

        return DB::transaction(function () use ($factura) {
            $hoy = now();

            $resultado = $this->numerador->siguienteNumero($factura->serie, $hoy);

            $factura->numero = $resultado['numero'];
            $factura->numero_completo = $resultado['numeroCompleto'];
            $factura->fecha_expedicion = $hoy->toDateString();
            $factura->fecha_vencimiento = VencimientoFactura::calcular($hoy->toDateString());
            $factura->estado = EstadoFactura::Emitida;
            $factura->save();

            FacturaEvento::create([
                'tenant_id' => $factura->tenant_id,
                'factura_id' => $factura->id,
                'tipo_evento' => 'emitida',
                'detalle' => [
                    'numero_completo' => $factura->numero_completo,
                    'fecha_expedicion' => $factura->fecha_expedicion->toDateString(),
                ],
                'ocurrido_at' => $hoy,
            ]);

            if ($factura->es_rectificativa) {
                $original = $factura->facturaRectificada;
                $original->estado = EstadoFactura::Rectificada;
                $original->save();

                FacturaEvento::create([
                    'tenant_id' => $original->tenant_id,
                    'factura_id' => $original->id,
                    'tipo_evento' => 'rectificada',
                    'detalle' => [
                        'rectificativa_id' => $factura->id,
                        'numero_completo' => $factura->numero_completo,
                    ],
                    'ocurrido_at' => $hoy,
                ]);
            }

            return $factura->refresh();
        });
    }

    private function validar(Factura $factura): void
    {
        if ($factura->estado !== EstadoFactura::Borrador) {
            throw new FacturaNoEmitibleException('Solo se pueden emitir facturas en borrador.');
        }

        if (! $factura->es_rectificativa && (float) $factura->base_total <= 0) {
            throw new FacturaNoEmitibleException('La factura no tiene líneas con importe.');
        }

        if (! $factura->cliente_nif || ! ($factura->cliente_nombre || $factura->cliente_razon_social) || ! $factura->cliente_direccion) {
            throw new FacturaNoEmitibleException('El cliente no tiene los datos fiscales mínimos (NIF, nombre/razón social y domicilio).');
        }
    }
}
