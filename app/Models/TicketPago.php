<?php

namespace App\Models;

use App\Enums\FormaPago;
use Database\Factories\TicketPagoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

/**
 * Un método de pago con su importe usado para cobrar un ticket (factura simplificada) en caja.
 * Un ticket puede tener uno (pago simple) o varios (pago dividido). Es un registro interno: no
 * se muestra en el PDF del ticket ni interviene en el módulo de cobros (`Pago`) ni en el dashboard.
 */
class TicketPago extends Model
{
    /** @use HasFactory<TicketPagoFactory> */
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'factura_id',
        'metodo',
        'importe',
    ];

    protected function casts(): array
    {
        return [
            'metodo' => FormaPago::class,
            'importe' => 'decimal:2',
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
}
