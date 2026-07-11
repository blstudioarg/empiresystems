<?php

namespace App\Models;

use Database\Factories\AlbaranLineaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class AlbaranLinea extends Model
{
    /** @use HasFactory<AlbaranLineaFactory> */
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'albaran_id',
        'presupuesto_linea_id',
        'articulo_id',
        'concepto',
        'unidad',
        'cantidad',
        'precio_unitario',
        'descuento_porcentaje',
        'base',
        'tipo_impositivo',
        'cuota_impuesto',
        'tipo_recargo',
        'cuota_recargo',
        'orden',
    ];

    protected function casts(): array
    {
        return [
            'cantidad' => 'decimal:4',
            'precio_unitario' => 'decimal:4',
            'descuento_porcentaje' => 'decimal:2',
            'base' => 'decimal:2',
            'tipo_impositivo' => 'decimal:2',
            'cuota_impuesto' => 'decimal:2',
            'tipo_recargo' => 'decimal:2',
            'cuota_recargo' => 'decimal:2',
            'orden' => 'integer',
        ];
    }

    public function albaran(): BelongsTo
    {
        return $this->belongsTo(Albaran::class);
    }

    public function presupuestoLinea(): BelongsTo
    {
        return $this->belongsTo(PresupuestoLinea::class);
    }

    public function articulo(): BelongsTo
    {
        return $this->belongsTo(Articulo::class);
    }
}
