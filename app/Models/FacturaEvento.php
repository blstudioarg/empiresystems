<?php

namespace App\Models;

use Database\Factories\FacturaEventoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

/**
 * Log append-only de operaciones sobre la factura (base del futuro encadenamiento Verifactu).
 * No expone actualización ni borrado: no se edita ni se borra ninguna fila.
 */
class FacturaEvento extends Model
{
    /** @use HasFactory<FacturaEventoFactory> */
    use BelongsToTenant, HasFactory;

    protected $table = 'factura_eventos';

    protected $fillable = [
        'tenant_id',
        'factura_id',
        'tipo_evento',
        'detalle',
        'huella',
        'ocurrido_at',
    ];

    protected function casts(): array
    {
        return [
            'detalle' => 'array',
            'ocurrido_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function factura(): BelongsTo
    {
        return $this->belongsTo(Factura::class);
    }
}
