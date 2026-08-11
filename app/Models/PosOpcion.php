<?php

namespace App\Models;

use Database\Factories\PosOpcionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

/**
 * Opción de un grupo (feature 038).
 *
 * `precio_defecto` es solo el punto de partida al asignarla a un artículo: el precio que se cobra
 * vive en el pivot `pos_articulo_opcion`, para que "Extra queso" pueda costar distinto según el
 * plato (FR-039).
 */
class PosOpcion extends Model
{
    /** @use HasFactory<PosOpcionFactory> */
    use BelongsToTenant, HasFactory, SoftDeletes;

    protected $table = 'pos_opciones';

    protected $fillable = [
        'tenant_id',
        'grupo_id',
        'nombre',
        'precio_defecto',
        'articulo_vinculado_id',
        'orden',
    ];

    protected function casts(): array
    {
        return [
            'precio_defecto' => 'decimal:2',
            'orden' => 'integer',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function grupo(): BelongsTo
    {
        return $this->belongsTo(PosOpcionGrupo::class, 'grupo_id');
    }

    public function articuloVinculado(): BelongsTo
    {
        return $this->belongsTo(Articulo::class, 'articulo_vinculado_id');
    }

    public function articulos(): BelongsToMany
    {
        return $this->belongsToMany(Articulo::class, 'pos_articulo_opcion', 'opcion_id', 'articulo_id')
            ->withPivot(['precio', 'orden'])
            ->withTimestamps();
    }
}
