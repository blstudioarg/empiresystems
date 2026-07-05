<?php

namespace App\Models;

use App\Enums\FormaPago;
use Database\Factories\PagoFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class Pago extends Model
{
    /** @use HasFactory<PagoFactory> */
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'factura_id',
        'fecha',
        'importe',
        'metodo',
        'referencia',
        'anulado_at',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'importe' => 'decimal:2',
            'metodo' => FormaPago::class,
            'anulado_at' => 'datetime',
        ];
    }

    public function factura(): BelongsTo
    {
        return $this->belongsTo(Factura::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function scopeVigentes(Builder $query): Builder
    {
        return $query->whereNull('anulado_at');
    }

    public function estaAnulado(): bool
    {
        return $this->anulado_at !== null;
    }
}
