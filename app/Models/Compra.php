<?php

namespace App\Models;

use App\Enums\EstadoB2b;
use App\Enums\EstadoCompra;
use App\Enums\OrigenCompra;
use Database\Factories\CompraFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class Compra extends Model
{
    /** @use HasFactory<CompraFactory> */
    use BelongsToTenant, HasFactory, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'proveedor_id',
        'numero_documento',
        'fecha',
        'estado',
        'base_total',
        'cuota_impuesto_total',
        'total',
        'notas',
        'origen',
        'formato_recepcion',
        'archivo_recibido_path',
        'estado_b2b',
        'estado_b2b_fecha',
        'confirmada_at',
        'anulada_at',
    ];

    protected function casts(): array
    {
        return [
            'estado' => EstadoCompra::class,
            'origen' => OrigenCompra::class,
            'estado_b2b' => EstadoB2b::class,
            'estado_b2b_fecha' => 'datetime',
            'fecha' => 'date',
            'base_total' => 'decimal:2',
            'cuota_impuesto_total' => 'decimal:2',
            'total' => 'decimal:2',
            'confirmada_at' => 'datetime',
            'anulada_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class);
    }

    public function lineas(): HasMany
    {
        return $this->hasMany(CompraLinea::class)->orderBy('orden');
    }

    public function movimientos(): HasMany
    {
        return $this->hasMany(MovimientoStock::class);
    }
}
