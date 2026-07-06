<?php

namespace App\Models;

use App\Enums\ResultadoUbicacionFichaje;
use App\Enums\TipoEventoFichaje;
use Database\Factories\FichajeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

/**
 * Ledger append-only de jornada. No expone rutas de edición/borrado: el único punto de
 * escritura es App\Services\RegistroFichajes. Las correcciones son eventos nuevos enlazados
 * vía `corrige_fichaje_id`, nunca un UPDATE del original.
 */
class Fichaje extends Model
{
    /** @use HasFactory<FichajeFactory> */
    use BelongsToTenant, HasFactory;

    protected $table = 'fichajes';

    protected $fillable = [
        'tenant_id',
        'miembro_equipo_id',
        'tipo',
        'ocurrido_at',
        'resultado_ubicacion',
        'distancia_metros',
        'precision_metros',
        'corrige_fichaje_id',
        'motivo',
        'registrado_por',
        'ip_origen',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'tipo' => TipoEventoFichaje::class,
            'ocurrido_at' => 'datetime',
            'resultado_ubicacion' => ResultadoUbicacionFichaje::class,
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function miembro(): BelongsTo
    {
        return $this->belongsTo(MiembroEquipo::class, 'miembro_equipo_id');
    }

    public function corrigeA(): BelongsTo
    {
        return $this->belongsTo(Fichaje::class, 'corrige_fichaje_id');
    }

    public function correcciones(): HasMany
    {
        return $this->hasMany(Fichaje::class, 'corrige_fichaje_id');
    }
}
