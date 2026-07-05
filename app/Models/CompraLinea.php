<?php

namespace App\Models;

use Database\Factories\CompraLineaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class CompraLinea extends Model
{
    /** @use HasFactory<CompraLineaFactory> */
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'compra_id',
        'articulo_id',
        'concepto',
        'unidad',
        'cantidad',
        'precio_unitario',
        'base',
        'tipo_impositivo',
        'cuota_impuesto',
        'orden',
    ];

    protected function casts(): array
    {
        return [
            'cantidad' => 'decimal:4',
            'precio_unitario' => 'decimal:4',
            'base' => 'decimal:2',
            'tipo_impositivo' => 'decimal:2',
            'cuota_impuesto' => 'decimal:2',
        ];
    }

    public function compra(): BelongsTo
    {
        return $this->belongsTo(Compra::class);
    }

    public function articulo(): BelongsTo
    {
        return $this->belongsTo(Articulo::class);
    }
}
