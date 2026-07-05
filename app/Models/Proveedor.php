<?php

namespace App\Models;

use Database\Factories\ProveedorFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class Proveedor extends Model
{
    /** @use HasFactory<ProveedorFactory> */
    use BelongsToTenant, HasFactory, SoftDeletes;

    protected $table = 'proveedores';

    protected $fillable = [
        'tenant_id',
        'nombre',
        'razon_social',
        'nif',
        'direccion',
        'cp',
        'ciudad',
        'provincia',
        'pais',
        'email',
        'telefono',
        'notas',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function compras(): HasMany
    {
        return $this->hasMany(Compra::class);
    }
}
