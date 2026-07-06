<?php

namespace App\Support;

use App\Exceptions\AsignacionHorarioSolapadaException;
use App\Models\AsignacionHorario;
use App\Models\Horario;
use App\Models\MiembroEquipo;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Único punto de escritura de `asignaciones_horario` (D2/R2): valida que la nueva vigencia no
 * se solape con un rango cerrado existente y cierra automáticamente la asignación abierta
 * anterior (FR-009/FR-010), todo en una transacción.
 */
class AsignadorHorario
{
    public function asignar(MiembroEquipo $miembro, Horario $horario, Carbon $vigenteDesde): AsignacionHorario
    {
        return DB::transaction(function () use ($miembro, $horario, $vigenteDesde) {
            $vigenteDesde = $vigenteDesde->copy()->startOfDay();

            $abierta = AsignacionHorario::where('miembro_equipo_id', $miembro->id)
                ->whereNull('vigente_hasta')
                ->first();

            if ($abierta && $vigenteDesde->lte($abierta->vigente_desde)) {
                throw new AsignacionHorarioSolapadaException(
                    'La fecha de vigencia debe ser posterior al inicio de la asignación actualmente vigente.'
                );
            }

            $solapaCerrada = AsignacionHorario::where('miembro_equipo_id', $miembro->id)
                ->whereNotNull('vigente_hasta')
                ->whereDate('vigente_desde', '<=', $vigenteDesde)
                ->whereDate('vigente_hasta', '>=', $vigenteDesde)
                ->exists();

            if ($solapaCerrada) {
                throw new AsignacionHorarioSolapadaException(
                    'La fecha de vigencia se solapa con una asignación histórica ya cerrada de este miembro.'
                );
            }

            if ($abierta) {
                $abierta->update(['vigente_hasta' => $vigenteDesde->copy()->subDay()->toDateString()]);
            }

            return AsignacionHorario::create([
                'tenant_id' => $miembro->tenant_id,
                'miembro_equipo_id' => $miembro->id,
                'horario_id' => $horario->id,
                'vigente_desde' => $vigenteDesde->toDateString(),
                'vigente_hasta' => null,
            ]);
        });
    }
}
