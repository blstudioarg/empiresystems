<?php

namespace App\Models;

use App\Enums\AccionLogActividad;
use App\Enums\EntidadLogActividad;
use App\Enums\ResultadoLogActividad;
use Database\Factories\LogActividadFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

/**
 * Log append-only de actividad de usuarios del tenant (auditoría general de uso).
 * No expone actualización ni borrado: no se edita ni se borra ninguna fila.
 */
class LogActividad extends Model
{
    /** @use HasFactory<LogActividadFactory> */
    use BelongsToTenant, HasFactory;

    protected $table = 'logs_actividad';

    protected $fillable = [
        'tenant_id',
        'usuario_id',
        'usuario_nombre',
        'accion',
        'resultado',
        'ip_origen',
        'user_agent',
        'entidad_tipo',
        'entidad_id',
        'descripcion',
        'ocurrido_at',
    ];

    protected function casts(): array
    {
        return [
            'accion' => AccionLogActividad::class,
            'resultado' => ResultadoLogActividad::class,
            'entidad_tipo' => EntidadLogActividad::class,
            'ocurrido_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}
