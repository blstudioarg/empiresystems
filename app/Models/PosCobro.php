<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

/**
 * Una emisión realizada sobre una cuenta (feature 038): una si se cobra entera, varias si se
 * cobra por partes. Guarda el % de suplemento de zona aplicado, congelado (FR-051).
 */
class PosCobro extends Model
{
    use BelongsToTenant, HasFactory;

    protected $table = 'pos_cobros';

    protected $fillable = [
        'tenant_id',
        'cuenta_id',
        'factura_id',
        'zona_suplemento_aplicado',
        'cobrado_por',
    ];

    protected function casts(): array
    {
        return ['zona_suplemento_aplicado' => 'decimal:2'];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function cuenta(): BelongsTo
    {
        return $this->belongsTo(PosCuenta::class, 'cuenta_id');
    }

    public function factura(): BelongsTo
    {
        return $this->belongsTo(Factura::class, 'factura_id');
    }

    public function lineas(): HasMany
    {
        return $this->hasMany(PosCobroLinea::class, 'cobro_id');
    }
}
