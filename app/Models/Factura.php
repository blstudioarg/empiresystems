<?php

namespace App\Models;

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

    public function lineas(): HasMany
    {
        return $this->hasMany(FacturaLinea::class)->orderBy('orden');
    }

    public function impuestos(): HasMany
    {
        return $this->hasMany(FacturaImpuesto::class);
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
}
