<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class CategoriaArticulo extends Model
{
    use BelongsToTenant;

    protected $table = 'categorias_articulo';

    protected $fillable = [
        'tenant_id',
        'nombre',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function articulos(): HasMany
    {
        return $this->hasMany(Articulo::class, 'categoria_id');
    }
}
