<?php

namespace App\Models;

use App\Services\PrecuentaCuenta;
use Database\Factories\PosPrecuentaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

/**
 * Precuenta emitida sobre una cuenta abierta (feature 049). **No es un documento fiscal**: no tiene
 * número, serie, huella Verifactu ni QR (docs/02-facturacion-espana.md §3.2).
 *
 * Solo alta (FR-013): una precuenta entregada al cliente es un hecho, no un estado. Una cuenta con
 * precuenta que acaba anulada sin cobrarse tiene que seguir siendo detectable, así que ni la
 * anulación ni el cobro ni la transferencia pueden tocar estas filas.
 *
 * `lineas` y `total` son la **foto** de lo impreso: el PDF se regenera desde aquí, nunca desde la
 * cuenta viva. Las huellas las calcula {@see PrecuentaCuenta}.
 */
class PosPrecuenta extends Model
{
    /** @use HasFactory<PosPrecuentaFactory> */
    use BelongsToTenant, HasFactory;

    protected $table = 'pos_precuentas';

    protected $fillable = [
        'tenant_id',
        'cuenta_id',
        'mesa_id',
        'mesa_nombre',
        'zona_nombre',
        'usuario_id',
        'emitida_en',
        'cuenta_version',
        'huella_consumo',
        'huella_pendiente',
        'reimpresion',
        'regimen_impositivo',
        'suplemento_zona',
        'comensales',
        'lineas',
        'total',
    ];

    protected function casts(): array
    {
        return [
            'emitida_en' => 'datetime',
            'cuenta_version' => 'integer',
            'reimpresion' => 'boolean',
            'suplemento_zona' => 'decimal:2',
            'comensales' => 'integer',
            'lineas' => 'array',
            'total' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function () {
            throw new LogicException('Una precuenta emitida no se modifica: emite una nueva.');
        });

        static::deleting(function () {
            throw new LogicException('Una precuenta emitida no se borra: el registro es append-only.');
        });
    }

    public function cuenta(): BelongsTo
    {
        return $this->belongsTo(PosCuenta::class, 'cuenta_id');
    }

    public function mesa(): BelongsTo
    {
        return $this->belongsTo(PosMesa::class, 'mesa_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}
