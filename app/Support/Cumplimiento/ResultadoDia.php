<?php

namespace App\Support\Cumplimiento;

use App\Enums\VeredictoCumplimiento;
use Carbon\Carbon;

/**
 * Resultado de cumplimiento de un miembro para un día concreto (derivado, no persistido).
 */
final class ResultadoDia
{
    public function __construct(
        public readonly Carbon $fecha,
        public readonly float $horasPrevistas,
        public readonly float $horasTrabajadas,
        public readonly bool $incidencia,
        public readonly VeredictoCumplimiento $veredicto,
        public readonly int $minutosRetraso,
        public readonly float $diferenciaHoras,
        /**
         * Desglose informativo de `horasTrabajadas` (no afecta el veredicto, ver
         * ServicioCumplimiento::evaluarDia): cuánto de lo trabajado cae dentro de algún tramo
         * previsto vs. fuera de cualquier tramo. `horasDentroHorario + horasFueraHorario` siempre
         * suma `horasTrabajadas`.
         */
        public readonly float $horasDentroHorario = 0.0,
        public readonly float $horasFueraHorario = 0.0,
    ) {}
}
