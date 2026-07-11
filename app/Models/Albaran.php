<?php

namespace App\Models;

use App\Enums\EstadoAlbaran;
use App\Enums\RegimenImpositivo;
use Database\Factories\AlbaranFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class Albaran extends Model
{
    /** @use HasFactory<AlbaranFactory> */
    use BelongsToTenant, HasFactory, SoftDeletes;

    protected $table = 'albaranes';

    protected $fillable = [
        'tenant_id',
        'numero',
        'presupuesto_id',
        'cliente_id',
        'estado',
        'receptor_nombre',
        'receptor_nif',
        'receptor_direccion',
        'receptor_cp',
        'receptor_ciudad',
        'receptor_provincia',
        'receptor_pais',
        'fecha_entrega',
        'regimen_impositivo',
        'aplica_recargo',
        'base_total',
        'cuota_impuesto_total',
        'cuota_recargo_total',
        'total',
        'convertido_a_factura_id',
        'notas',
    ];

    protected function casts(): array
    {
        return [
            'estado' => EstadoAlbaran::class,
            'regimen_impositivo' => RegimenImpositivo::class,
            'aplica_recargo' => 'boolean',
            'fecha_entrega' => 'date',
            'base_total' => 'decimal:2',
            'cuota_impuesto_total' => 'decimal:2',
            'cuota_recargo_total' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function presupuesto(): BelongsTo
    {
        return $this->belongsTo(Presupuesto::class);
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function facturaConvertida(): BelongsTo
    {
        return $this->belongsTo(Factura::class, 'convertido_a_factura_id');
    }

    public function lineas(): HasMany
    {
        return $this->hasMany(AlbaranLinea::class)->orderBy('orden');
    }
}
