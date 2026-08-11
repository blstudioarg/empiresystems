<?php

namespace App\Models;

use Database\Factories\PosOpcionGrupoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

/**
 * Grupo de opciones reutilizable (feature 038). Ej.: "Punto de cocción", "Guarnición".
 *
 * `obligatorio` es un atajo de `min_selecciones ≥ 1`; se guardan los dos porque la interfaz
 * razona con el interruptor y el validador con los números.
 */
class PosOpcionGrupo extends Model
{
    /** @use HasFactory<PosOpcionGrupoFactory> */
    use BelongsToTenant, HasFactory, SoftDeletes;

    protected $table = 'pos_opcion_grupos';

    protected $fillable = [
        'tenant_id',
        'nombre',
        'min_selecciones',
        'max_selecciones',
        'obligatorio',
        'orden',
    ];

    protected function casts(): array
    {
        return [
            'min_selecciones' => 'integer',
            'max_selecciones' => 'integer',
            'obligatorio' => 'boolean',
            'orden' => 'integer',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function opciones(): HasMany
    {
        return $this->hasMany(PosOpcion::class, 'grupo_id')->orderBy('orden');
    }

    public function articulos(): BelongsToMany
    {
        return $this->belongsToMany(Articulo::class, 'pos_articulo_grupo', 'grupo_id', 'articulo_id')
            ->withPivot(['orden'])
            ->withTimestamps();
    }
}
