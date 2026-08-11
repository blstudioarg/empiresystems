<?php

namespace App\Models;

use Database\Factories\PosCuentaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

/**
 * Cuenta abierta de mesa (feature 038). **No es una factura en borrador** (FR-017): no tiene
 * número ni serie, no aparece en el listado de tickets y no genera registro Verifactu. Al cobrar,
 * {@see \App\Services\CobradorCuenta} traduce sus unidades pendientes a una emisión normal.
 *
 * Las transiciones válidas son `abierta → cerrada` (al saldarse la última unidad) y
 * `abierta → anulada`. De `cerrada` o `anulada` no se sale.
 */
class PosCuenta extends Model
{
    /** @use HasFactory<PosCuentaFactory> */
    use BelongsToTenant, HasFactory;

    public const ESTADO_ABIERTA = 'abierta';

    public const ESTADO_CERRADA = 'cerrada';

    public const ESTADO_ANULADA = 'anulada';

    protected $table = 'pos_cuentas';

    protected $fillable = [
        'tenant_id',
        'mesa_id',
        'comensales',
        'estado',
        'abierta_por',
        'abierta_en',
        'cerrada_en',
        'version',
        'cliente_id',
        'cliente_nombre',
        'cliente_razon_social',
        'cliente_nif',
        'cliente_direccion',
        'notas',
    ];

    protected function casts(): array
    {
        return [
            'abierta_en' => 'datetime',
            'cerrada_en' => 'datetime',
            'comensales' => 'integer',
            'version' => 'integer',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function mesa(): BelongsTo
    {
        return $this->belongsTo(PosMesa::class, 'mesa_id');
    }

    public function abiertaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'abierta_por');
    }

    public function lineas(): HasMany
    {
        return $this->hasMany(PosCuentaLinea::class, 'cuenta_id')->orderBy('orden');
    }

    public function cobros(): HasMany
    {
        return $this->hasMany(PosCobro::class, 'cuenta_id');
    }

    public function estaAbierta(): bool
    {
        return $this->estado === self::ESTADO_ABIERTA;
    }

    /**
     * Importe bruto **pendiente de cobro** (no el consumido): es lo que la sala muestra en la
     * mesa (FR-028) y lo que se compara con el tope de la simplificada (FR-025).
     */
    public function pendiente(): float
    {
        return round($this->lineas->sum(fn (PosCuentaLinea $linea) => $linea->brutoPendiente()), 2);
    }

    /** Una cuenta está saldada cuando ninguna línea tiene unidades pendientes. */
    public function estaSaldada(): bool
    {
        return $this->lineas->every(fn (PosCuentaLinea $linea) => $linea->cantidadPendiente() <= 0);
    }
}
