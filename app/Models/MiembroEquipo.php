<?php

namespace App\Models;

use Database\Factories\MiembroEquipoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

/**
 * Perfil de empleado 1:1 con un User con login (D7). No es ledger: editable por Admin.
 */
class MiembroEquipo extends Model
{
    /** @use HasFactory<MiembroEquipoFactory> */
    use BelongsToTenant, HasFactory, SoftDeletes;

    protected $table = 'miembros_equipo';

    protected $fillable = [
        'tenant_id',
        'user_id',
        'puesto',
        'trabajo_direccion',
        'trabajo_latitud',
        'trabajo_longitud',
        'distancia_max_metros',
        'casa_direccion',
        'casa_latitud',
        'casa_longitud',
        'distancia_casa_trabajo_metros',
        'activo',
        'dado_baja_at',
    ];

    protected function casts(): array
    {
        return [
            'trabajo_latitud' => 'decimal:7',
            'trabajo_longitud' => 'decimal:7',
            'casa_latitud' => 'decimal:7',
            'casa_longitud' => 'decimal:7',
            'activo' => 'boolean',
            'dado_baja_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function fichajes(): HasMany
    {
        return $this->hasMany(Fichaje::class);
    }

    public function asignacionesHorario(): HasMany
    {
        return $this->hasMany(AsignacionHorario::class);
    }

    public function tieneUbicacionTrabajo(): bool
    {
        return $this->trabajo_latitud !== null && $this->trabajo_longitud !== null;
    }
}
