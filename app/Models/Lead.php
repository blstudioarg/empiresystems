<?php

namespace App\Models;

use App\Enums\EstadoLead;
use App\Enums\OrigenLead;
use Database\Factories\LeadFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class Lead extends Model
{
    /** @use HasFactory<LeadFactory> */
    use BelongsToTenant, HasFactory, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'nombre',
        'empresa',
        'email',
        'telefono',
        'estado',
        'origen',
        'asignado_a',
        'convertido_a_cliente_id',
        'motivo_descarte',
        'notas',
    ];

    protected function casts(): array
    {
        return [
            'estado' => EstadoLead::class,
            'origen' => OrigenLead::class,
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function asignadoA(): BelongsTo
    {
        return $this->belongsTo(User::class, 'asignado_a');
    }

    public function clienteConvertido(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'convertido_a_cliente_id');
    }

    public function notasLead(): HasMany
    {
        return $this->hasMany(LeadNota::class)->orderByDesc('created_at');
    }

    public function oportunidades(): HasMany
    {
        return $this->hasMany(Oportunidad::class);
    }
}
