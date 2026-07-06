<?php

namespace App\Models;

use Database\Factories\HorarioFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

/**
 * Plantilla de cuadrante reutilizable por tenant (feature 025). Editar sus tramos afecta a
 * todos los miembros que la tienen asignada (según la vigencia de su asignación).
 */
class Horario extends Model
{
    /** @use HasFactory<HorarioFactory> */
    use BelongsToTenant, HasFactory, SoftDeletes;

    protected $table = 'horarios';

    protected $fillable = [
        'tenant_id',
        'nombre',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }

    public function tramos(): HasMany
    {
        return $this->hasMany(HorarioTramo::class);
    }

    public function asignaciones(): HasMany
    {
        return $this->hasMany(AsignacionHorario::class);
    }

    public function horasPrevistasDia(int $diaSemana): float
    {
        return $this->tramos
            ->where('dia_semana', $diaSemana)
            ->sum(fn (HorarioTramo $tramo) => $tramo->horas());
    }

    public function horasPrevistasSemana(): float
    {
        return $this->tramos->sum(fn (HorarioTramo $tramo) => $tramo->horas());
    }
}
