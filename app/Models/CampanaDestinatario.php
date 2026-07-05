<?php

namespace App\Models;

use App\Enums\EstadoDestinatario;
use Database\Factories\CampanaDestinatarioFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class CampanaDestinatario extends Model
{
    /** @use HasFactory<CampanaDestinatarioFactory> */
    use BelongsToTenant, HasFactory;

    protected $table = 'campana_destinatarios';

    protected $fillable = [
        'tenant_id',
        'campana_id',
        'cliente_id',
        'email',
        'estado',
        'error',
        'enviado_at',
    ];

    protected function casts(): array
    {
        return [
            'estado' => EstadoDestinatario::class,
            'enviado_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function campana(): BelongsTo
    {
        return $this->belongsTo(Campana::class);
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }
}
