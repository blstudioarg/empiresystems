<?php

namespace App\Models;

use Database\Factories\PosZonaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

/**
 * Zona de sala (feature 038). `BelongsToTenant` aplica el global scope de tenant, igual que en el
 * resto de modelos de negocio del proyecto (Principio I).
 *
 * El nombre no tiene ningún significado para el sistema (FR-009): "Terraza" es un ejemplo, no un
 * concepto con privilegios.
 */
class PosZona extends Model
{
    /** @use HasFactory<PosZonaFactory> */
    use BelongsToTenant, HasFactory, SoftDeletes;

    protected $table = 'pos_zonas';

    protected $fillable = [
        'tenant_id',
        'nombre',
        'suplemento_porcentaje',
        'columnas',
        'filas',
        'celdas_inactivas',
        'orden',
        'version',
    ];

    protected function casts(): array
    {
        return [
            'suplemento_porcentaje' => 'decimal:2',
            'columnas' => 'integer',
            'filas' => 'integer',
            // La mascara de recorte se lee y se escribe ENTERA (D1 de la feature 042): nunca se
            // consulta ni se filtra desde SQL, asi que un `array` plano es la representacion justa.
            'celdas_inactivas' => 'array',
            'orden' => 'integer',
            'version' => 'integer',
        ];
    }

    /**
     * Celdas que no son sala, siempre como array (la columna es `nullable` porque MySQL no admite
     * DEFAULT en JSON; ausente significa "sin recortes", no "sin dato").
     *
     * @return array<int, string>
     */
    public function celdasInactivas(): array
    {
        return array_values((array) ($this->celdas_inactivas ?? []));
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function mesas(): HasMany
    {
        return $this->hasMany(PosMesa::class, 'zona_id');
    }
}
