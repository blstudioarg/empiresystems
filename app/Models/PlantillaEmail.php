<?php

namespace App\Models;

use Database\Factories\PlantillaEmailFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class PlantillaEmail extends Model
{
    /** @use HasFactory<PlantillaEmailFactory> */
    use BelongsToTenant, HasFactory, SoftDeletes;

    protected $table = 'plantillas_email';

    protected $fillable = [
        'tenant_id',
        'titulo',
        'asunto',
        'cuerpo',
        'activa',
    ];

    protected function casts(): array
    {
        return [
            'activa' => 'boolean',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function campanas(): HasMany
    {
        return $this->hasMany(Campana::class);
    }
}
