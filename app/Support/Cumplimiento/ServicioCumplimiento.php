<?php

namespace App\Support\Cumplimiento;

use App\Enums\TipoEventoFichaje;
use App\Enums\VeredictoCumplimiento;
use App\Models\Horario;
use App\Models\HorarioTramo;
use App\Models\MiembroEquipo;
use App\Services\InformeJornada;
use App\Support\ConfigFichajes;
use App\Support\RangoFechas;
use App\Support\ResolutorHorario;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Cruza el horario planificado (feature 025) con el ledger real de `fichajes` (feature 024)
 * para clasificar el cumplimiento de cada día (R6). Se calcula siempre al vuelo (FR-019a), sin
 * persistir resultados.
 */
class ServicioCumplimiento
{
    public function __construct(private readonly InformeJornada $informeJornada) {}

    public function resolverHorario(MiembroEquipo $miembro, Carbon $dia): ?Horario
    {
        return ResolutorHorario::vigente($miembro, $dia);
    }

    public function evaluarDia(MiembroEquipo $miembro, Carbon $dia): ResultadoDia
    {
        $dia = $dia->copy()->startOfDay();

        $horario = $this->resolverHorario($miembro, $dia);
        $tramos = $horario
            ? $horario->tramos->where('dia_semana', $dia->dayOfWeekIso)->sortBy('hora_inicio')->values()
            : collect();
        $horasPrevistas = round($tramos->sum(fn (HorarioTramo $tramo) => $tramo->horas()), 2);

        $eventos = $this->informeJornada->eventosEfectivos($miembro, $dia->copy()->startOfDay(), $dia->copy()->endOfDay());

        [$horasTrabajadas, $incidencia] = $this->horasTrabajadas($eventos);

        $horasDentroHorario = round($this->segundosSolapados($this->intervalosTrabajo($eventos), $this->intervalosTramos($tramos, $dia)) / 3600, 2);
        $horasFueraHorario = round(max(0, $horasTrabajadas - $horasDentroHorario), 2);

        if ($horasPrevistas <= 0.0) {
            // Sin tramos previstos hoy: no hay ventana contra la cual solapar, así que todo lo
            // trabajado es, por definición, "fuera de horario" ($horasDentroHorario da 0 arriba
            // porque intervalosTramos() ya viene vacío).
            return new ResultadoDia($dia, 0.0, $horasTrabajadas, $incidencia, VeredictoCumplimiento::Libre, 0, $horasTrabajadas, $horasDentroHorario, $horasFueraHorario);
        }

        $entradas = $eventos->where('tipo', TipoEventoFichaje::Entrada)->values();

        if ($entradas->isEmpty()) {
            return new ResultadoDia($dia, $horasPrevistas, 0.0, false, VeredictoCumplimiento::Ausencia, 0, -$horasPrevistas, $horasDentroHorario, $horasFueraHorario);
        }

        $tenantId = $miembro->tenant_id;
        $toleranciaRetraso = ConfigFichajes::toleranciaRetrasoMin($tenantId);
        $toleranciaExceso = ConfigFichajes::toleranciaExcesoMin($tenantId);

        $minutosRetraso = $this->minutosRetraso($tramos, $entradas, $toleranciaRetraso);
        $diferenciaHoras = round($horasTrabajadas - $horasPrevistas, 2);

        $veredicto = match (true) {
            $minutosRetraso > 0 => VeredictoCumplimiento::Retraso,
            $diferenciaHoras < -($toleranciaExceso / 60) => VeredictoCumplimiento::Parcial,
            $diferenciaHoras > ($toleranciaExceso / 60) => VeredictoCumplimiento::Exceso,
            default => VeredictoCumplimiento::Cumplido,
        };

        return new ResultadoDia($dia, $horasPrevistas, $horasTrabajadas, $incidencia, $veredicto, $minutosRetraso, $diferenciaHoras, $horasDentroHorario, $horasFueraHorario);
    }

    /**
     * @return Collection<int, ResultadoDia>
     */
    public function evaluarRango(MiembroEquipo $miembro, RangoFechas $rango): Collection
    {
        $resultados = collect();
        $cursor = $rango->desde->copy()->startOfDay();
        $hasta = $rango->hasta->copy()->startOfDay();

        while ($cursor->lte($hasta)) {
            $resultados->push($this->evaluarDia($miembro, $cursor->copy()));
            $cursor->addDay();
        }

        return $resultados;
    }

    /**
     * Empareja cada tramo previsto (en orden) con la Entrada del mismo índice (aproximación de
     * "cercanía a la ventana" para turnos partidos, R6): si falta una Entrada para un tramo, se
     * cuenta como retraso equivalente a la duración completa del tramo.
     *
     * @return int minutos de retraso agregados de todos los tramos del día
     */
    private function minutosRetraso(Collection $tramos, Collection $entradas, int $toleranciaMin): int
    {
        $minutosRetraso = 0;

        foreach ($tramos as $indice => $tramo) {
            $entrada = $entradas->get($indice);

            if ($entrada === null) {
                $minutosRetraso += (int) round($tramo->horas() * 60);

                continue;
            }

            $inicioTramoMin = $this->minutosDelDia($tramo->hora_inicio);
            $entradaMin = $entrada->ocurrido_at->hour * 60 + $entrada->ocurrido_at->minute;
            $retraso = $entradaMin - $inicioTramoMin - $toleranciaMin;

            if ($retraso > 0) {
                $minutosRetraso += $retraso;
            }
        }

        return $minutosRetraso;
    }

    /**
     * Empareja Entrada→Salida y resta pausas, igual semántica que
     * `InformeJornada::segundosEfectivos`. Marca incidencia (FR-015a) si queda una Entrada sin
     * su Salida correspondiente: esa porción NO se computa (no se infiere una hora de salida).
     *
     * @return array{0: float, 1: bool}
     */
    private function horasTrabajadas(Collection $eventos): array
    {
        $totalSegundos = 0;
        $entradaAbierta = null;
        $pausaInicio = null;
        $incidencia = false;

        foreach ($eventos as $evento) {
            if ($evento->tipo === TipoEventoFichaje::Entrada) {
                if ($entradaAbierta !== null) {
                    $incidencia = true;
                }
                $entradaAbierta = $evento->ocurrido_at;
            } elseif ($evento->tipo === TipoEventoFichaje::Salida && $entradaAbierta !== null) {
                $totalSegundos += $evento->ocurrido_at->getTimestamp() - $entradaAbierta->getTimestamp();
                $entradaAbierta = null;
            } elseif ($evento->tipo === TipoEventoFichaje::InicioPausa) {
                $pausaInicio = $evento->ocurrido_at;
            } elseif ($evento->tipo === TipoEventoFichaje::FinPausa && $pausaInicio !== null) {
                $totalSegundos -= $evento->ocurrido_at->getTimestamp() - $pausaInicio->getTimestamp();
                $pausaInicio = null;
            }
        }

        if ($entradaAbierta !== null) {
            $incidencia = true;
        }

        return [round(max(0, $totalSegundos) / 3600, 2), $incidencia];
    }

    /**
     * Tramos realmente trabajados (Entrada/FinPausa → InicioPausa/Salida), como intervalos
     * [inicio, fin] en timestamp — a diferencia de `horasTrabajadas()`, que solo necesita el total,
     * acá hace falta cada sub-intervalo por separado para poder solaparlo contra los tramos
     * previstos. Una Entrada sin Salida (jornada abierta / incidencia) no genera intervalo, misma
     * semántica que `horasTrabajadas()`.
     *
     * @return array<int, array{0: int, 1: int}>
     */
    private function intervalosTrabajo(Collection $eventos): array
    {
        $intervalos = [];
        $inicioSegmento = null;

        foreach ($eventos as $evento) {
            if ($evento->tipo === TipoEventoFichaje::Entrada || $evento->tipo === TipoEventoFichaje::FinPausa) {
                $inicioSegmento = $evento->ocurrido_at;
            } elseif ($inicioSegmento !== null
                && ($evento->tipo === TipoEventoFichaje::InicioPausa || $evento->tipo === TipoEventoFichaje::Salida)) {
                $intervalos[] = [$inicioSegmento->getTimestamp(), $evento->ocurrido_at->getTimestamp()];
                $inicioSegmento = null;
            }
        }

        return $intervalos;
    }

    /**
     * Tramos previstos del día como intervalos [inicio, fin] en timestamp, anclados a `$dia`.
     *
     * @return array<int, array{0: int, 1: int}>
     */
    private function intervalosTramos(Collection $tramos, Carbon $dia): array
    {
        return $tramos
            ->map(fn (HorarioTramo $tramo) => [
                $dia->copy()->setTimeFromTimeString($tramo->hora_inicio)->getTimestamp(),
                $dia->copy()->setTimeFromTimeString($tramo->hora_fin)->getTimestamp(),
            ])
            ->all();
    }

    /**
     * Suma de solapamiento entre dos listas de intervalos [inicio, fin] (segundos). Usado para
     * saber cuánto de lo trabajado cae dentro de algún tramo previsto (R6, desglose informativo).
     *
     * @param  array<int, array{0: int, 1: int}>  $intervalosA
     * @param  array<int, array{0: int, 1: int}>  $intervalosB
     */
    private function segundosSolapados(array $intervalosA, array $intervalosB): int
    {
        $total = 0;

        foreach ($intervalosA as [$inicioA, $finA]) {
            foreach ($intervalosB as [$inicioB, $finB]) {
                $inicio = max($inicioA, $inicioB);
                $fin = min($finA, $finB);

                if ($fin > $inicio) {
                    $total += $fin - $inicio;
                }
            }
        }

        return $total;
    }

    private function minutosDelDia(string $horaTime): int
    {
        [$horas, $minutos] = array_map('intval', explode(':', substr($horaTime, 0, 5)));

        return $horas * 60 + $minutos;
    }
}
