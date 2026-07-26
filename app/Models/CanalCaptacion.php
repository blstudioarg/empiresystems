<?php

namespace App\Models;

use Database\Factories\CanalCaptacionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

/**
 * Catálogo por tenant del canal de captación de un lead (feature 033, data-model.md §1). Sin
 * `softDeletes`: la desactivación (`activo = false`) es suficiente para conservar el histórico
 * (FR-013) sin necesitar borrado lógico.
 */
class CanalCaptacion extends Model
{
    /** @use HasFactory<CanalCaptacionFactory> */
    use BelongsToTenant, HasFactory;

    protected $table = 'canales_captacion';

    protected $fillable = [
        'tenant_id',
        'nombre',
        'activo',
        'orden',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
            'orden' => 'integer',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class, 'canal_captacion_id');
    }
}
