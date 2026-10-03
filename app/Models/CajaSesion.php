<?php

namespace App\Models;

use App\Services\CierreCaja;
use Database\Factories\CajaSesionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

/**
 * Sesión de caja del POS (feature 048): el periodo entre una apertura y un cierre.
 *
 * `abierta → cerrada` y de ahí no se sale. Una sesión cerrada es **inmutable** (invariante C2):
 * sus cifras son las que había en el cajón al cerrar y ningún cambio posterior puede moverlas. El
 * cierre lo hace {@see CierreCaja} en un único `save()` sobre la sesión todavía abierta; cualquier
 * escritura posterior lanza excepción.
 */
class CajaSesion extends Model
{
    /** @use HasFactory<CajaSesionFactory> */
    use BelongsToTenant, HasFactory;

    public const ESTADO_ABIERTA = 'abierta';

    public const ESTADO_CERRADA = 'cerrada';

    protected $table = 'caja_sesiones';

    protected $fillable = [
        'tenant_id',
        'estado',
        'abierta_marca',
        'fondo_inicial',
        'conteo_apertura',
        'abierta_por',
        'abierta_at',
        'cerrada_por',
        'cerrada_at',
        'num_tickets',
        'total_facturado',
        'efectivo_ventas',
        'entradas',
        'salidas',
        'efectivo_esperado',
        'efectivo_contado',
        'conteo_cierre',
        'descuadre',
        'observacion',
        'resumen',
    ];

    protected function casts(): array
    {
        return [
            'abierta_marca' => 'integer',
            'fondo_inicial' => 'decimal:2',
            'conteo_apertura' => 'array',
            'abierta_at' => 'datetime',
            'cerrada_at' => 'datetime',
            'num_tickets' => 'integer',
            'total_facturado' => 'decimal:2',
            'efectivo_ventas' => 'decimal:2',
            'entradas' => 'decimal:2',
            'salidas' => 'decimal:2',
            'efectivo_esperado' => 'decimal:2',
            'efectivo_contado' => 'decimal:2',
            'conteo_cierre' => 'array',
            'descuadre' => 'decimal:2',
            'resumen' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (CajaSesion $sesion) {
            if ($sesion->getOriginal('estado') === self::ESTADO_CERRADA) {
                throw new LogicException('Una caja cerrada no se puede modificar.');
            }
        });

        static::deleting(function () {
            throw new LogicException('Una sesión de caja no se puede borrar.');
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function abiertaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'abierta_por');
    }

    public function cerradaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cerrada_por');
    }

    public function movimientos(): HasMany
    {
        return $this->hasMany(CajaMovimiento::class, 'caja_sesion_id')->orderBy('created_at')->orderBy('id');
    }

    public function ticketPagos(): HasMany
    {
        return $this->hasMany(TicketPago::class, 'caja_sesion_id');
    }

    /** @param  Builder<CajaSesion>  $query */
    public function scopeAbierta(Builder $query): void
    {
        $query->where('estado', self::ESTADO_ABIERTA);
    }

    /** @param  Builder<CajaSesion>  $query */
    public function scopeCerrada(Builder $query): void
    {
        $query->where('estado', self::ESTADO_CERRADA);
    }

    public function estaAbierta(): bool
    {
        return $this->estado === self::ESTADO_ABIERTA;
    }

    /**
     * Estado del arqueo para pintarlo: `cuadra`, `sobra` o `falta`. Solo tiene sentido cerrada.
     */
    public function estadoArqueo(): ?string
    {
        if ($this->descuadre === null) {
            return null;
        }

        return self::estadoDeDescuadre((string) $this->descuadre);
    }

    public static function estadoDeDescuadre(string $descuadre): string
    {
        $centimos = (int) round(((float) $descuadre) * 100);

        return match (true) {
            $centimos > 0 => 'sobra',
            $centimos < 0 => 'falta',
            default => 'cuadra',
        };
    }
}
