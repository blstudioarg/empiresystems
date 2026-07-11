<?php

namespace App\Models;

use App\Enums\EstadoCobro;
use App\Enums\EstadoFactura;
use App\Enums\FormaPago;
use App\Enums\RegimenImpositivo;
use App\Enums\TipoFactura;
use App\Enums\TipoRectificacion;
use Database\Factories\FacturaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class Factura extends Model
{
    /** @use HasFactory<FacturaFactory> */
    use BelongsToTenant, HasFactory, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'serie_id',
        'numero',
        'numero_completo',
        'tipo',
        'estado',
        'es_rectificativa',
        'factura_rectificada_id',
        'motivo_rectificacion',
        'tipo_rectificacion',
        'cliente_id',
        'cliente_nombre',
        'cliente_razon_social',
        'cliente_nif',
        'cliente_direccion',
        'cliente_cp',
        'cliente_ciudad',
        'cliente_provincia',
        'cliente_pais',
        'fecha_expedicion',
        'fecha_operacion',
        'fecha_vencimiento',
        'forma_pago',
        'cuenta_bancaria_id',
        'cuenta_bancaria_banco',
        'cuenta_bancaria_iban',
        'cuenta_bancaria_titular',
        'moneda',
        'regimen_impositivo',
        'aplica_recargo',
        'base_total',
        'cuota_impuesto_total',
        'cuota_recargo_total',
        'irpf_porcentaje',
        'irpf_cuota',
        'total',
        'notas',
    ];

    protected function casts(): array
    {
        return [
            'tipo' => TipoFactura::class,
            'estado' => EstadoFactura::class,
            'es_rectificativa' => 'boolean',
            'tipo_rectificacion' => TipoRectificacion::class,
            'forma_pago' => FormaPago::class,
            'regimen_impositivo' => RegimenImpositivo::class,
            'aplica_recargo' => 'boolean',
            'fecha_expedicion' => 'date',
            'fecha_operacion' => 'date',
            'fecha_vencimiento' => 'date',
            'base_total' => 'decimal:2',
            'cuota_impuesto_total' => 'decimal:2',
            'cuota_recargo_total' => 'decimal:2',
            'irpf_porcentaje' => 'decimal:2',
            'irpf_cuota' => 'decimal:2',
            'total' => 'decimal:2',
            'registrada_at' => 'datetime',
            'estado_b2b_fecha' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function serie(): BelongsTo
    {
        return $this->belongsTo(Serie::class);
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function cuentaBancaria(): BelongsTo
    {
        return $this->belongsTo(CuentaBancaria::class);
    }

    public function lineas(): HasMany
    {
        return $this->hasMany(FacturaLinea::class)->orderBy('orden');
    }

    public function impuestos(): HasMany
    {
        return $this->hasMany(FacturaImpuesto::class);
    }

    /**
     * Albaranes consolidados en esta factura (research D4, feature 029) — solo lectura; determina
     * si `EmisorFacturas::moverStock()` debe omitir el movimiento de stock (ya ocurrió al entregar
     * cada albarán).
     */
    public function albaranes(): HasMany
    {
        return $this->hasMany(Albaran::class, 'convertido_a_factura_id');
    }

    public function eventos(): HasMany
    {
        return $this->hasMany(FacturaEvento::class)->orderBy('ocurrido_at');
    }

    public function facturaRectificada(): BelongsTo
    {
        return $this->belongsTo(Factura::class, 'factura_rectificada_id');
    }

    public function rectificativa(): HasOne
    {
        return $this->hasOne(Factura::class, 'factura_rectificada_id');
    }

    public function pagos(): HasMany
    {
        return $this->hasMany(Pago::class);
    }

    public function pagosVigentes(): HasMany
    {
        return $this->pagos()->whereNull('anulado_at');
    }

    public function montoCobrado(): float
    {
        return round((float) $this->pagosVigentes()->sum('importe'), 2);
    }

    /**
     * Rectificativa ya emitida que rectifica esta factura, si existe (cualquier modalidad).
     * La original rectificada es SIEMPRE el documento de cobro, para que el usuario gestione
     * los cobros desde un único sitio sin tener que deducir la modalidad ni buscar la
     * rectificativa. El importe cobrable se ajusta según la modalidad (ver totalCobrable()).
     */
    public function rectificativaEmitida(): ?Factura
    {
        $rectificativa = $this->rectificativa;

        if ($rectificativa && $rectificativa->estado === EstadoFactura::Emitida) {
            return $rectificativa;
        }

        return null;
    }

    /**
     * Importe efectivo a cobrar sobre esta factura:
     * - Rectificada por SUSTITUCIÓN: el total de la rectificativa (reemplaza al original).
     * - Rectificada por DIFERENCIAS: el neto (total original + delta de la rectificativa).
     * - Resto: su propio total.
     */
    public function totalCobrable(): float
    {
        $rectificativa = ($this->estado === EstadoFactura::Rectificada) ? $this->rectificativaEmitida() : null;

        if ($rectificativa === null) {
            return round((float) $this->total, 2);
        }

        if ($rectificativa->tipo_rectificacion === TipoRectificacion::Sustitucion) {
            return round((float) $rectificativa->total, 2);
        }

        return round((float) $this->total + (float) $rectificativa->total, 2);
    }

    /**
     * ¿Sobre esta factura se gestionan cobros? La factura emitida normal, o la original ya
     * rectificada (por sustitución o por diferencias) — que cobra su importe efectivo. Una
     * rectificativa NUNCA admite cobros por sí misma: el cobro se gestiona siempre desde la
     * original, sea cual sea la modalidad.
     */
    public function admiteCobros(): bool
    {
        if ($this->es_rectificativa) {
            return false;
        }

        return $this->estado === EstadoFactura::Emitida
            || ($this->estado === EstadoFactura::Rectificada && $this->rectificativaEmitida() !== null);
    }

    public function saldoPendiente(): float
    {
        return round($this->totalCobrable() - $this->montoCobrado(), 2);
    }

    public function fueEnviada(): bool
    {
        return $this->eventos
            ->where('tipo_evento', 'envio_email')
            ->contains(fn (FacturaEvento $evento) => ($evento->detalle['resultado'] ?? null) === 'ok');
    }

    public function estadoCobro(): EstadoCobro
    {
        $totalCentimos = (int) round($this->totalCobrable() * 100);
        $cobradoCentimos = (int) round($this->montoCobrado() * 100);

        if ($cobradoCentimos <= 0) {
            return EstadoCobro::Pendiente;
        }

        if ($cobradoCentimos >= $totalCentimos) {
            return EstadoCobro::Cobrada;
        }

        return EstadoCobro::Parcial;
    }
}
