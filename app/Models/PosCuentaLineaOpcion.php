<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

/**
 * Opción concreta elegida para una línea (feature 038). Nombre, precio y artículo vinculado están
 * congelados: sobreviven al renombrado, al cambio de precio y al borrado de la opción.
 */
class PosCuentaLineaOpcion extends Model
{
    use BelongsToTenant, HasFactory;

    protected $table = 'pos_cuenta_linea_opciones';

    protected $fillable = [
        'tenant_id',
        'cuenta_linea_id',
        'opcion_id',
        'nombre',
        'precio',
        'articulo_vinculado_id',
    ];

    protected function casts(): array
    {
        return ['precio' => 'decimal:2'];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function linea(): BelongsTo
    {
        return $this->belongsTo(PosCuentaLinea::class, 'cuenta_linea_id');
    }

    public function articuloVinculado(): BelongsTo
    {
        return $this->belongsTo(Articulo::class, 'articulo_vinculado_id');
    }
}
