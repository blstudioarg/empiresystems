<?php

namespace App\Services;

use App\Enums\TipoEventoFichaje;
use App\Models\Fichaje;
use App\Models\MiembroEquipo;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Agrupa los fichajes de un miembro en un periodo y calcula las horas efectivas (FR-004): las
 * correcciones sustituyen al evento original para el cómputo, pero la traza completa (original +
 * corrección) se expone siempre para el informe/exportación (FR-016).
 */
class InformeJornada
{
    /**
     * @return array{eventos: Collection<int, Fichaje>, total_horas: float}
     */
    public function generar(MiembroEquipo $miembro, Carbon $desde, Carbon $hasta): array
    {
        $eventos = Fichaje::where('miembro_equipo_id', $miembro->id)
            ->whereBetween('ocurrido_at', [$desde, $hasta])
            ->orderBy('ocurrido_at')
            ->orderBy('id')
            ->get();

        $efectivos = $this->aplicarCorrecciones($eventos);

        return [
            'eventos' => $eventos,
            'total_horas' => round($this->segundosEfectivos($efectivos) / 3600, 2),
        ];
    }

    /**
     * Eventos efectivos (correcciones aplicadas) de un miembro en un rango, expuesto para que
     * `App\Support\Cumplimiento\ServicioCumplimiento` (feature 025) reutilice la misma
     * semántica de correcciones sin duplicar el emparejamiento.
     *
     * @return Collection<int, object{tipo: TipoEventoFichaje, ocurrido_at: Carbon}>
     */
    public function eventosEfectivos(MiembroEquipo $miembro, Carbon $desde, Carbon $hasta): Collection
    {
        $eventos = Fichaje::where('miembro_equipo_id', $miembro->id)
            ->whereBetween('ocurrido_at', [$desde, $hasta])
            ->orderBy('ocurrido_at')
            ->orderBy('id')
            ->get();

        return $this->aplicarCorrecciones($eventos);
    }

    /**
     * @return Collection<int, object{tipo: TipoEventoFichaje, ocurrido_at: Carbon}>
     */
    private function aplicarCorrecciones(Collection $eventos): Collection
    {
        $correcciones = $eventos->whereNotNull('corrige_fichaje_id')->keyBy('corrige_fichaje_id');

        return $eventos
            ->whereNull('corrige_fichaje_id')
            ->map(function (Fichaje $original) use ($correcciones) {
                $correccion = $correcciones->get($original->id);

                return (object) [
                    'tipo' => $correccion->tipo ?? $original->tipo,
                    'ocurrido_at' => $correccion->ocurrido_at ?? $original->ocurrido_at,
                ];
            })
            ->sortBy('ocurrido_at')
            ->values();
    }

    private function segundosEfectivos(Collection $eventos): int
    {
        $total = 0;
        $entradaAbierta = null;
        $pausaInicio = null;

        foreach ($eventos as $evento) {
            if ($evento->tipo === TipoEventoFichaje::Entrada) {
                $entradaAbierta = $evento->ocurrido_at;
            } elseif ($evento->tipo === TipoEventoFichaje::Salida && $entradaAbierta !== null) {
                // Resta de timestamps (no diffInSeconds): evita depender del signo por defecto
                // de diffInX, que ha cambiado entre versiones mayores de Carbon.
                $total += $evento->ocurrido_at->getTimestamp() - $entradaAbierta->getTimestamp();
                $entradaAbierta = null;
            } elseif ($evento->tipo === TipoEventoFichaje::InicioPausa) {
                $pausaInicio = $evento->ocurrido_at;
            } elseif ($evento->tipo === TipoEventoFichaje::FinPausa && $pausaInicio !== null) {
                $total -= $evento->ocurrido_at->getTimestamp() - $pausaInicio->getTimestamp();
                $pausaInicio = null;
            }
        }

        return max(0, $total);
    }
}
