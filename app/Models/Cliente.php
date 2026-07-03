<?php

namespace App\Models;

use App\Enums\TipoCliente;
use Database\Factories\ClienteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class Cliente extends Model
{
    /** @use HasFactory<ClienteFactory> */
    use BelongsToTenant, HasFactory, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'tipo',
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
        'aplica_recargo_equivalencia',
        'notas',
    ];

    protected function casts(): array
    {
        return [
            'tipo' => TipoCliente::class,
            'aplica_recargo_equivalencia' => 'boolean',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
