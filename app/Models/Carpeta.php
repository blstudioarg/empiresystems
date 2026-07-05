<?php

namespace App\Models;

use Database\Factories\CarpetaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class Carpeta extends Model
{
    /** @use HasFactory<CarpetaFactory> */
    use BelongsToTenant, HasFactory, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'parent_id',
        'nombre',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function padre(): BelongsTo
    {
        return $this->belongsTo(Carpeta::class, 'parent_id');
    }

    public function subcarpetas(): HasMany
    {
        return $this->hasMany(Carpeta::class, 'parent_id');
    }

    public function archivos(): HasMany
    {
        return $this->hasMany(Archivo::class, 'carpeta_id');
    }

    /**
     * IDs de todas las subcarpetas descendientes (recursivo), para validar que mover una carpeta
     * no cree un ciclo (una carpeta no puede terminar dentro de sí misma ni de un descendiente).
     *
     * @return array<int, int>
     */
    public function descendientesIds(): array
    {
        $ids = [];

        foreach ($this->subcarpetas as $hija) {
            $ids[] = $hija->id;
            $ids = array_merge($ids, $hija->descendientesIds());
        }

        return $ids;
    }
}
