<?php

namespace App\Models;

use Database\Factories\CajaMovimientoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

/**
 * Entrada o salida manual de efectivo durante una sesión de caja (feature 048).
 *
 * Solo alta (FR-008): un movimiento equivocado se corrige con otro del tipo contrario, de modo que
 * el informe del cierre cuenta la historia completa en vez de un estado final retocado.
 */
class CajaMovimiento extends Model
{
    /** @use HasFactory<CajaMovimientoFactory> */
    use BelongsToTenant, HasFactory;

    public const TIPO_ENTRADA = 'entrada';

    public const TIPO_SALIDA = 'salida';

    protected $table = 'caja_movimientos';

    protected $fillable = [
        'tenant_id',
        'caja_sesion_id',
        'tipo',
        'importe',
        'motivo',
        'usuario_id',
    ];

    protected function casts(): array
    {
        return [
            'importe' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function () {
            throw new LogicException('Un movimiento de caja no se modifica: registra uno inverso.');
        });

        static::deleting(function () {
            throw new LogicException('Un movimiento de caja no se borra: registra uno inverso.');
        });
    }

    public function sesion(): BelongsTo
    {
        return $this->belongsTo(CajaSesion::class, 'caja_sesion_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}
