<?php

namespace App\Support;

use App\Models\AsignacionHorario;
use App\Models\Horario;
use App\Models\MiembroEquipo;
use Carbon\Carbon;

/**
 * Resuelve el horario aplicable de un miembro en una fecha dada (R2): la asignación con
 * `vigente_desde <= F AND (vigente_hasta IS NULL OR vigente_hasta >= F)`.
 */
class ResolutorHorario
{
    public static function vigente(MiembroEquipo $miembro, Carbon $fecha): ?Horario
    {
        return static::asignacionVigente($miembro, $fecha)?->horario;
    }

    public static function asignacionVigente(MiembroEquipo $miembro, Carbon $fecha): ?AsignacionHorario
    {
        $fecha = $fecha->copy()->startOfDay();

        // whereDate (no where() con toDateString()): las columnas `date` se guardan con el
        // formato datetime completo de Eloquent ("Y-m-d H:i:s") en drivers sin tipo DATE nativo
        // (SQLite), así que comparar contra un string solo-fecha con `where()` falla ahí aunque
        // funcione en MySQL. `whereDate()` normaliza ambos lados en cualquier driver.
        return AsignacionHorario::where('miembro_equipo_id', $miembro->id)
            ->whereDate('vigente_desde', '<=', $fecha)
            ->where(function ($query) use ($fecha) {
                $query->whereNull('vigente_hasta')->orWhereDate('vigente_hasta', '>=', $fecha);
            })
            ->with('horario')
            ->first();
    }
}
