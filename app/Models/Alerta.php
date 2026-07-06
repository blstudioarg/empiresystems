<?php

namespace App\Models;

use App\Enums\EstadoAlerta;
use App\Enums\TipoAlerta;
use Database\Factories\AlertaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

/**
 * Creada por App\Services\RegistroFichajes al registrar un fichaje Fuera (D11). Se consulta y
 * cambia de estado; nunca se borra.
 */
class Alerta extends Model
{
    /** @use HasFactory<AlertaFactory> */
    use BelongsToTenant, HasFactory;

    protected $table = 'alertas';

    protected $fillable = [
        'tenant_id',
        'miembro_equipo_id',
        'fichaje_id',
        'tipo',
        'distancia_metros',
        'referencia_fecha',
        'estado',
        'resuelta_por',
        'resuelta_at',
    ];

    protected function casts(): array
    {
        return [
            'tipo' => TipoAlerta::class,
            'estado' => EstadoAlerta::class,
            'referencia_fecha' => 'date',
            'resuelta_at' => 'datetime',
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

    public function fichaje(): BelongsTo
    {
        return $this->belongsTo(Fichaje::class);
    }
}
