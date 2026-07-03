<?php

namespace App\Models;

use App\Enums\TipoFactura;
use Database\Factories\SerieFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class Serie extends Model
{
    /** @use HasFactory<SerieFactory> */
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'codigo',
        'tipo',
        'ejercicio',
        'proximo_numero',
        'formato',
        'activa',
    ];

    protected function casts(): array
    {
        return [
            'tipo' => TipoFactura::class,
            'proximo_numero' => 'integer',
            'activa' => 'boolean',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function facturas(): HasMany
    {
        return $this->hasMany(Factura::class);
    }

    public static function activaPorTipo(TipoFactura $tipo): Serie
    {
        return self::where('tipo', $tipo)->where('activa', true)->firstOrFail();
    }
}
