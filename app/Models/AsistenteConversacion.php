<?php

namespace App\Models;

use Database\Factories\AsistenteConversacionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

/**
 * Un hilo de diálogo entre una persona y el asistente (feature 045).
 *
 * `BelongsToTenant` aporta el scope global del Principio I. **Además** hay que acotar por
 * `user_id`: las conversaciones son privadas de cada persona, no del tenant, así que el scope de
 * tenant por sí solo no basta (ver `paraUsuario`).
 */
class AsistenteConversacion extends Model
{
    /** @use HasFactory<AsistenteConversacionFactory> */
    use BelongsToTenant, HasFactory;

    protected $table = 'asistente_conversaciones';

    protected $fillable = [
        'tenant_id',
        'user_id',
        'titulo',
        'resumen',
        'resumido_hasta_mensaje_id',
        'ultima_actividad_en',
    ];

    protected function casts(): array
    {
        return [
            'ultima_actividad_en' => 'datetime',
            'resumido_hasta_mensaje_id' => 'integer',
        ];
    }

    /** @return HasMany<AsistenteMensaje, $this> */
    public function mensajes(): HasMany
    {
        return $this->hasMany(AsistenteMensaje::class, 'conversacion_id')->orderBy('id');
    }

    /** @return BelongsTo<User, $this> */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Único punto por el que deben pasar las consultas del historial: el scope de tenant no separa a
     * una persona de otra dentro de la misma empresa, y aquí eso importa.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeParaUsuario($query, User $usuario)
    {
        return $query->where('user_id', $usuario->id);
    }

    public function estaCompactada(): bool
    {
        return $this->resumen !== null && $this->resumen !== '';
    }
}
