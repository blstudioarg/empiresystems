<?php

namespace App\Models;

use App\Enums\TipoArticulo;
use Database\Factories\ArticuloFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class Articulo extends Model
{
    /** @use HasFactory<ArticuloFactory> */
    use BelongsToTenant, HasFactory, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'tipo',
        'sku',
        'nombre',
        'descripcion',
        'unidad',
        'precio',
        'tipo_impositivo',
        'gestion_stock',
        'stock_actual',
        'stock_minimo',
        'aplica_recargo_equivalencia',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'tipo' => TipoArticulo::class,
            'precio' => 'decimal:4',
            'tipo_impositivo' => 'decimal:2',
            'gestion_stock' => 'boolean',
            'stock_actual' => 'decimal:4',
            'stock_minimo' => 'decimal:4',
            'aplica_recargo_equivalencia' => 'boolean',
            'activo' => 'boolean',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
