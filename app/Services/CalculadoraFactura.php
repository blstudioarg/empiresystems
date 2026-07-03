<?php

namespace App\Services;

use App\Enums\RegimenImpositivo;
use App\Support\TiposImpositivos;

class CalculadoraFactura
{
    /**
     * @param  array<int, array{cantidad: float, precioUnitario: float, descuentoPorcentaje: ?float, tipoImpositivo: float}>  $lineas
     */
    public function calcular(
        RegimenImpositivo $regimen,
        bool $aplicaRecargo,
        ?float $irpfPorcentaje,
        array $lineas,
    ): ResultadoCalculoFactura {
        $tipoImpuestoIndirecto = $regimen->value;
        $aplicaRecargoEfectivo = $aplicaRecargo && $regimen === RegimenImpositivo::Iva;

        $lineasCalculadas = [];
        $impuestosPorClave = [];
        $baseTotal = 0.0;
        $cuotaImpuestoTotal = 0.0;
        $cuotaRecargoTotal = 0.0;

        foreach ($lineas as $linea) {
            $base = round($linea['cantidad'] * $linea['precioUnitario'], 2);

            $descuento = $linea['descuentoPorcentaje'] ?? 0;
            if ($descuento > 0) {
                $base = round($base * (1 - $descuento / 100), 2);
            }

            $tipoImpositivo = (float) $linea['tipoImpositivo'];
            $cuotaImpuesto = round($base * $tipoImpositivo / 100, 2);

            $tipoRecargo = null;
            $cuotaRecargo = 0.0;
            if ($aplicaRecargoEfectivo) {
                $tipoRecargo = TiposImpositivos::recargoParaTipoIva($tipoImpositivo);
                $cuotaRecargo = round($base * $tipoRecargo / 100, 2);
            }

            $lineasCalculadas[] = [
                'base' => $base,
                'cuotaImpuesto' => $cuotaImpuesto,
                'tipoRecargo' => $tipoRecargo,
                'cuotaRecargo' => $cuotaRecargo,
            ];

            $baseTotal += $base;
            $cuotaImpuestoTotal += $cuotaImpuesto;
            $cuotaRecargoTotal += $cuotaRecargo;

            $claveImpuesto = $tipoImpuestoIndirecto.'|'.$tipoImpositivo;
            if (! isset($impuestosPorClave[$claveImpuesto])) {
                $impuestosPorClave[$claveImpuesto] = [
                    'tipoImpuesto' => $tipoImpuestoIndirecto,
                    'porcentaje' => $tipoImpositivo,
                    'baseImponible' => 0.0,
                    'cuota' => 0.0,
                ];
            }
            $impuestosPorClave[$claveImpuesto]['baseImponible'] += $base;
            $impuestosPorClave[$claveImpuesto]['cuota'] += $cuotaImpuesto;

            if ($aplicaRecargoEfectivo && $tipoRecargo > 0) {
                $claveRecargo = 'recargo|'.$tipoRecargo;
                if (! isset($impuestosPorClave[$claveRecargo])) {
                    $impuestosPorClave[$claveRecargo] = [
                        'tipoImpuesto' => 'recargo',
                        'porcentaje' => $tipoRecargo,
                        'baseImponible' => 0.0,
                        'cuota' => 0.0,
                    ];
                }
                $impuestosPorClave[$claveRecargo]['baseImponible'] += $base;
                $impuestosPorClave[$claveRecargo]['cuota'] += $cuotaRecargo;
            }
        }

        $baseTotal = round($baseTotal, 2);
        $cuotaImpuestoTotal = round($cuotaImpuestoTotal, 2);
        $cuotaRecargoTotal = round($cuotaRecargoTotal, 2);

        $irpfCuota = 0.0;
        if ($irpfPorcentaje !== null && $irpfPorcentaje > 0) {
            $irpfCuota = round($baseTotal * $irpfPorcentaje / 100, 2);
            $impuestosPorClave['irpf|'.$irpfPorcentaje] = [
                'tipoImpuesto' => 'irpf',
                'porcentaje' => $irpfPorcentaje,
                'baseImponible' => $baseTotal,
                'cuota' => $irpfCuota,
            ];
        }

        $total = round($baseTotal + $cuotaImpuestoTotal + $cuotaRecargoTotal - $irpfCuota, 2);

        return new ResultadoCalculoFactura(
            lineas: $lineasCalculadas,
            impuestos: array_values($impuestosPorClave),
            baseTotal: $baseTotal,
            cuotaImpuestoTotal: $cuotaImpuestoTotal,
            cuotaRecargoTotal: $cuotaRecargoTotal,
            irpfCuota: $irpfCuota,
            total: $total,
        );
    }
}
