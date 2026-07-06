<?php

namespace App\Models;

use Carbon\Carbon;
use Database\Factories\AsignacionHorarioFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

/**
 * Vínculo miembro↔horario con vigencia temporal (feature 025, D2/R2): patrón "slowly changing
 * dimension" tipo 2. El horario aplicable de un miembro en una fecha F es la fila con
 * `vigente_desde <= F AND (vigente_hasta IS NULL OR vigente_hasta >= F)`.
 */
class AsignacionHorario extends Model
{
    /** @use HasFactory<AsignacionHorarioFactory> */
    use BelongsToTenant, HasFactory;

    protected $table = 'asignaciones_horario';

    protected $fillable = [
        'tenant_id',
        'miembro_equipo_id',
        'horario_id',
        'vigente_desde',
        'vigente_hasta',
    ];

    protected function casts(): array
    {
        return [
            'vigente_desde' => 'date',
            'vigente_hasta' => 'date',
        ];
    }

    public function miembro(): BelongsTo
    {
        return $this->belongsTo(MiembroEquipo::class, 'miembro_equipo_id');
    }

    public function horario(): BelongsTo
    {
        return $this->belongsTo(Horario::class);
    }

    public function vigenteEn(Carbon $fecha): bool
    {
        $fecha = $fecha->copy()->startOfDay();

        return $this->vigente_desde->lte($fecha) && ($this->vigente_hasta === null || $this->vigente_hasta->gte($fecha));
    }

    public function esVigente(): bool
    {
        return $this->vigente_hasta === null;
    }
}
