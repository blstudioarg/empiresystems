<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

/**
 * Detalle de qué unidades saldó cada cobro (feature 038). Existe para poder auditar y, sobre
 * todo, para hacer imposible el doble cobro: por línea, la suma de `cantidad` aquí debe igualar
 * `pos_cuenta_lineas.cantidad_saldada` (FR-033).
 */
class PosCobroLinea extends Model
{
    use BelongsToTenant, HasFactory;

    protected $table = 'pos_cobro_lineas';

    protected $fillable = [
        'tenant_id',
        'cobro_id',
        'cuenta_linea_id',
        'cantidad',
    ];

    protected function casts(): array
    {
        return ['cantidad' => 'decimal:2'];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function cobro(): BelongsTo
    {
        return $this->belongsTo(PosCobro::class, 'cobro_id');
    }

    public function cuentaLinea(): BelongsTo
    {
        return $this->belongsTo(PosCuentaLinea::class, 'cuenta_linea_id');
    }
}
