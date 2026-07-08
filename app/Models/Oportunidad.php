<?php

namespace App\Models;

use App\Enums\EtapaOportunidad;
use Database\Factories\OportunidadFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class Oportunidad extends Model
{
    /** @use HasFactory<OportunidadFactory> */
    use BelongsToTenant, HasFactory, SoftDeletes;

    protected $table = 'oportunidades';

    protected $fillable = [
        'tenant_id',
        'titulo',
        'lead_id',
        'cliente_id',
        'etapa',
        'importe_estimado',
        'asignado_a',
        'motivo_perdida',
        'cerrada_at',
        'notas',
    ];

    protected function casts(): array
    {
        return [
            'etapa' => EtapaOportunidad::class,
            'importe_estimado' => 'decimal:2',
            'cerrada_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function asignadoA(): BelongsTo
    {
        return $this->belongsTo(User::class, 'asignado_a');
    }

    public function presupuestos(): HasMany
    {
        return $this->hasMany(Presupuesto::class);
    }
}
