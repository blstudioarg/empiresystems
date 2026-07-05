<?php

namespace App\Models;

use Database\Factories\CuentaBancariaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class CuentaBancaria extends Model
{
    /** @use HasFactory<CuentaBancariaFactory> */
    use BelongsToTenant, HasFactory, SoftDeletes;

    protected $table = 'cuentas_bancarias';

    protected $fillable = [
        'tenant_id',
        'banco_id',
        'alias',
        'iban',
        'titular',
        'activa',
    ];

    protected function casts(): array
    {
        return [
            'activa' => 'boolean',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function banco(): BelongsTo
    {
        return $this->belongsTo(Banco::class);
    }
}
