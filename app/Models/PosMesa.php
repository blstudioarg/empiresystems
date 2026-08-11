<?php

namespace App\Models;

use Database\Factories\PosMesaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

/**
 * Mesa de una zona (feature 038).
 *
 * No tiene columna de estado: `cuentaAbierta()` deriva libre/ocupada de los datos, que es la
 * única forma de que la sala no pueda mentir.
 */
class PosMesa extends Model
{
    /** @use HasFactory<PosMesaFactory> */
    use BelongsToTenant, HasFactory, SoftDeletes;

    protected $table = 'pos_mesas';

    protected $fillable = [
        'tenant_id',
        'zona_id',
        'nombre',
        'orden',
        'fila',
        'columna',
        'forma',
        'tamano',
    ];

    protected function casts(): array
    {
        return [
            'orden' => 'integer',
            'fila' => 'integer',
            'columna' => 'integer',
            'forma' => 'string',
            'tamano' => 'string',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function zona(): BelongsTo
    {
        return $this->belongsTo(PosZona::class, 'zona_id');
    }

    public function cuentas(): HasMany
    {
        return $this->hasMany(PosCuenta::class, 'mesa_id');
    }

    public function cuentaAbierta(): HasMany
    {
        return $this->cuentas()->where('estado', PosCuenta::ESTADO_ABIERTA);
    }
}
