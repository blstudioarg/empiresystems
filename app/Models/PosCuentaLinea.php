<?php

namespace App\Models;

use Database\Factories\PosCuentaLineaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

/**
 * Línea de una cuenta abierta (feature 038). Concepto, precio y tipo impositivo están congelados
 * al añadir: el catálogo puede cambiar (o el artículo desaparecer) sin alterar lo ya comandado.
 *
 * Invariante: `0 ≤ cantidad_saldada ≤ cantidad`.
 */
class PosCuentaLinea extends Model
{
    /** @use HasFactory<PosCuentaLineaFactory> */
    use BelongsToTenant, HasFactory;

    protected $table = 'pos_cuenta_lineas';

    protected $fillable = [
        'tenant_id',
        'cuenta_id',
        'articulo_id',
        'concepto',
        'unidad',
        'cantidad',
        'precio_unitario',
        'suplemento_opciones',
        'tipo_impositivo',
        'cantidad_saldada',
        'orden',
    ];

    protected function casts(): array
    {
        return [
            'cantidad' => 'decimal:2',
            'precio_unitario' => 'decimal:2',
            'suplemento_opciones' => 'decimal:2',
            'tipo_impositivo' => 'decimal:2',
            'cantidad_saldada' => 'decimal:2',
            'orden' => 'integer',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function cuenta(): BelongsTo
    {
        return $this->belongsTo(PosCuenta::class, 'cuenta_id');
    }

    public function articulo(): BelongsTo
    {
        return $this->belongsTo(Articulo::class, 'articulo_id');
    }

    public function opciones(): HasMany
    {
        return $this->hasMany(PosCuentaLineaOpcion::class, 'cuenta_linea_id');
    }

    /**
     * Precio unitario efectivo: el del artículo más los suplementos de las opciones elegidas.
     * El suplemento de zona NO entra aquí — se aplica al cobrar, con el valor vigente y el de la
     * zona donde se cobra (FR-051).
     */
    public function precioEfectivo(): float
    {
        return round((float) $this->precio_unitario + (float) $this->suplemento_opciones, 2);
    }

    public function cantidadPendiente(): float
    {
        return round((float) $this->cantidad - (float) $this->cantidad_saldada, 2);
    }

    /** Importe bruto (impuesto incluido) de las unidades todavía sin cobrar. */
    public function brutoPendiente(): float
    {
        return $this->brutoDe($this->cantidadPendiente());
    }

    public function brutoDe(float $unidades, float $suplementoZonaPorcentaje = 0.0): float
    {
        $precio = round($this->precioEfectivo() * (1 + $suplementoZonaPorcentaje / 100), 2);
        $base = round($precio * $unidades, 2);

        return round($base + round($base * (float) $this->tipo_impositivo / 100, 2), 2);
    }
}
