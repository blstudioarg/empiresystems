<?php

namespace App\Models;

use Database\Factories\TraduccionCorreccionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

/**
 * Corrección manual de un tenant sobre la traducción de un texto (feature 050, FR-016/017/018).
 *
 * Prevalece sobre {@see Traduccion} **solo** para su tenant (Principio I). La sincronización
 * automática nunca escribe aquí, así que no puede pisarla (FR-017). Se puede borrar («Restaurar
 * automática»): es configuración del tenant, no un registro fiscal.
 */
class TraduccionCorreccion extends Model
{
    /** @use HasFactory<TraduccionCorreccionFactory> */
    use BelongsToTenant, HasFactory;

    protected $table = 'traduccion_correcciones';

    protected $fillable = [
        'tenant_id',
        'idioma',
        'hash',
        'texto',
        'traduccion',
        'corregida_por',
    ];

    /** @return BelongsTo<User, $this> */
    public function corregidaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'corregida_por');
    }
}
