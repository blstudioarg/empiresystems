<?php

namespace App\Models;

use App\Enums\EstadoCampana;
use Database\Factories\CampanaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class Campana extends Model
{
    /** @use HasFactory<CampanaFactory> */
    use BelongsToTenant, HasFactory;

    protected $table = 'campanas';

    protected $fillable = [
        'tenant_id',
        'user_id',
        'plantilla_email_id',
        'asunto',
        'cuerpo',
        'estado',
        'total_destinatarios',
        'enviados',
        'fallidos',
        'enviada_at',
    ];

    protected function casts(): array
    {
        return [
            'estado' => EstadoCampana::class,
            'total_destinatarios' => 'integer',
            'enviados' => 'integer',
            'fallidos' => 'integer',
            'enviada_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function plantillaEmail(): BelongsTo
    {
        return $this->belongsTo(PlantillaEmail::class);
    }

    public function destinatarios(): HasMany
    {
        return $this->hasMany(CampanaDestinatario::class);
    }
}
