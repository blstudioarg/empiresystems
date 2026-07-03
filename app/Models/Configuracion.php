<?php

namespace App\Models;

use Database\Factories\ConfiguracionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class Configuracion extends Model
{
    /** @use HasFactory<ConfiguracionFactory> */
    use BelongsToTenant, HasFactory;

    protected $table = 'configuraciones';

    protected $fillable = [
        'tenant_id',
        'clave',
        'valor',
        'tipo',
        'grupo',
        'descripcion',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
