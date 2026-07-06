<?php

namespace App\Models;

use Database\Factories\HorarioTramoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

/**
 * Tramo de trabajo de un horario (feature 025). `dia_semana` 1=lunes...7=domingo (ISO-8601,
 * `Carbon::dayOfWeekIso`). Varios tramos el mismo día = turno partido.
 */
class HorarioTramo extends Model
{
    /** @use HasFactory<HorarioTramoFactory> */
    use BelongsToTenant, HasFactory;

    protected $table = 'horario_tramos';

    protected $fillable = [
        'tenant_id',
        'horario_id',
        'dia_semana',
        'hora_inicio',
        'hora_fin',
    ];

    protected function casts(): array
    {
        return [
            'dia_semana' => 'integer',
        ];
    }

    public function horario(): BelongsTo
    {
        return $this->belongsTo(Horario::class);
    }

    /**
     * Horas del tramo derivadas de hora_inicio/hora_fin (columnas TIME, formato H:i:s).
     */
    public function horas(): float
    {
        [$hIni, $mIni] = array_map('intval', explode(':', substr($this->hora_inicio, 0, 5)));
        [$hFin, $mFin] = array_map('intval', explode(':', substr($this->hora_fin, 0, 5)));

        return (($hFin * 60 + $mFin) - ($hIni * 60 + $mIni)) / 60;
    }
}
