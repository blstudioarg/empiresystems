<?php

namespace App\Services;

class ResultadoCalculoFactura
{
    /**
     * @param  array<int, array{base: float, cuotaImpuesto: float, tipoRecargo: ?float, cuotaRecargo: float}>  $lineas
     * @param  array<int, array{tipoImpuesto: string, porcentaje: float, baseImponible: float, cuota: float}>  $impuestos
     */
    public function __construct(
        public readonly array $lineas,
        public readonly array $impuestos,
        public readonly float $baseTotal,
        public readonly float $cuotaImpuestoTotal,
        public readonly float $cuotaRecargoTotal,
        public readonly float $irpfCuota,
        public readonly float $total,
    ) {}
}
