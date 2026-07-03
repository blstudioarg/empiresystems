<?php

namespace App\Models;

use App\Enums\TipoImpuesto;
use Database\Factories\FacturaImpuestoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class FacturaImpuesto extends Model
{
    /** @use HasFactory<FacturaImpuestoFactory> */
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'factura_id',
        'tipo_impuesto',
        'porcentaje',
        'base_imponible',
        'cuota',
    ];

    protected function casts(): array
    {
        return [
            'tipo_impuesto' => TipoImpuesto::class,
            'porcentaje' => 'decimal:2',
            'base_imponible' => 'decimal:2',
            'cuota' => 'decimal:2',
        ];
    }

    public function factura(): BelongsTo
    {
        return $this->belongsTo(Factura::class);
    }
}
