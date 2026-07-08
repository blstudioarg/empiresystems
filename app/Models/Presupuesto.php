<?php

namespace App\Models;

use App\Enums\EstadoPresupuesto;
use App\Enums\RegimenImpositivo;
use Database\Factories\PresupuestoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class Presupuesto extends Model
{
    /** @use HasFactory<PresupuestoFactory> */
    use BelongsToTenant, HasFactory, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'numero',
        'oportunidad_id',
        'cliente_id',
        'lead_id',
        'estado',
        'receptor_nombre',
        'receptor_nif',
        'receptor_direccion',
        'receptor_cp',
        'receptor_ciudad',
        'receptor_provincia',
        'receptor_pais',
        'fecha_emision',
        'fecha_validez',
        'fecha_envio',
        'regimen_impositivo',
        'aplica_recargo',
        'base_total',
        'cuota_impuesto_total',
        'cuota_recargo_total',
        'irpf_porcentaje',
        'irpf_cuota',
        'total',
        'convertido_a_factura_id',
        'notas',
    ];

    protected function casts(): array
    {
        return [
            'estado' => EstadoPresupuesto::class,
            'regimen_impositivo' => RegimenImpositivo::class,
            'aplica_recargo' => 'boolean',
            'fecha_emision' => 'date',
            'fecha_validez' => 'date',
            'fecha_envio' => 'datetime',
            'base_total' => 'decimal:2',
            'cuota_impuesto_total' => 'decimal:2',
            'cuota_recargo_total' => 'decimal:2',
            'irpf_porcentaje' => 'decimal:2',
            'irpf_cuota' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function oportunidad(): BelongsTo
    {
        return $this->belongsTo(Oportunidad::class);
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function facturaConvertida(): BelongsTo
    {
        return $this->belongsTo(Factura::class, 'convertido_a_factura_id');
    }

    public function lineas(): HasMany
    {
        return $this->hasMany(PresupuestoLinea::class)->orderBy('orden');
    }
}
