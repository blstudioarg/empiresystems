<?php

namespace App\Models;

use App\Enums\OrigenMovimientoStock;
use App\Enums\TipoMovimientoStock;
use Database\Factories\MovimientoStockFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

/**
 * Ledger append-only del inventario. No expone rutas de edición/borrado: el único punto de
 * escritura es App\Services\RegistroMovimientoStock. Las correcciones son movimientos inversos.
 */
class MovimientoStock extends Model
{
    /** @use HasFactory<MovimientoStockFactory> */
    use BelongsToTenant, HasFactory;

    protected $table = 'movimientos_stock';

    protected $fillable = [
        'tenant_id',
        'articulo_id',
        'tipo',
        'cantidad',
        'stock_resultante',
        'origen',
        'factura_id',
        'compra_id',
        'motivo',
        'ocurrido_at',
    ];

    protected function casts(): array
    {
        return [
            'tipo' => TipoMovimientoStock::class,
            'origen' => OrigenMovimientoStock::class,
            'cantidad' => 'decimal:4',
            'stock_resultante' => 'decimal:4',
            'ocurrido_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function articulo(): BelongsTo
    {
        return $this->belongsTo(Articulo::class);
    }

    public function compra(): BelongsTo
    {
        return $this->belongsTo(Compra::class);
    }

    public function factura(): BelongsTo
    {
        return $this->belongsTo(Factura::class);
    }
}
