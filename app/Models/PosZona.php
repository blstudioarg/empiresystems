<?php

namespace App\Models;

use Database\Factories\PosZonaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

/**
 * Zona de sala (feature 038). `BelongsToTenant` aplica el global scope de tenant, igual que en el
 * resto de modelos de negocio del proyecto (Principio I).
 *
 * El nombre no tiene ningún significado para el sistema (FR-009): "Terraza" es un ejemplo, no un
 * concepto con privilegios.
 */
class PosZona extends Model
{
    /** @use HasFactory<PosZonaFactory> */
    use BelongsToTenant, HasFactory, SoftDeletes;

    protected $table = 'pos_zonas';

    protected $fillable = [
        'tenant_id',
        'nombre',
        'suplemento_porcentaje',
        'orden',
        'version',
    ];

    protected function casts(): array
    {
        return [
            'suplemento_porcentaje' => 'decimal:2',
            'orden' => 'integer',
            'version' => 'integer',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function mesas(): HasMany
    {
        return $this->hasMany(PosMesa::class, 'zona_id');
    }
}
