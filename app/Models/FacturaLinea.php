<?php

namespace App\Models;

use App\Enums\CalificacionOperacion;
use App\Enums\CausaExencion;
use Database\Factories\FacturaLineaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class FacturaLinea extends Model
{
    /** @use HasFactory<FacturaLineaFactory> */
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'factura_id',
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
        'calificacion_operacion',
        'causa_exencion',
        'mencion_legal',
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
            'calificacion_operacion' => CalificacionOperacion::class,
            'causa_exencion' => CausaExencion::class,
            'orden' => 'integer',
        ];
    }

    public function factura(): BelongsTo
    {
        return $this->belongsTo(Factura::class);
    }

    public function articulo(): BelongsTo
    {
        return $this->belongsTo(Articulo::class);
    }
}
