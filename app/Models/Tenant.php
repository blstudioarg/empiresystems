<?php

namespace App\Models;

use App\Enums\RegimenImpositivo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Stancl\Tenancy\Database\Models\Domain;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;

class Tenant extends BaseTenant
{
    use HasFactory;

    public $incrementing = true;

    protected $keyType = 'int';

    protected $fillable = [
        'nombre_comercial',
        'razon_social',
        'nif',
        'direccion',
        'cp',
        'ciudad',
        'provincia',
        'pais',
        'regimen_impositivo',
        'email',
        'activo',
        'logo_path',
        'logo_mini_path',
        'login_logo_path',
        'login_imagen_path',
        'logo_facturacion_path',
        'favicon_path',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
            'regimen_impositivo' => RegimenImpositivo::class,
        ];
    }

    public function domains(): HasMany
    {
        return $this->hasMany(Domain::class);
    }

    /**
     * Regla de negocio: un único dominio por tenant (data-model.md), aunque la relación
     * subyacente sea hasMany (idiomática de stancl, deja la puerta abierta a alias futuros).
     */
    public function dominio(): ?Domain
    {
        return $this->domains()->first();
    }

    protected static function booted(): void
    {
        static::created(function (self $tenant) {
            Serie::firstOrCreate(
                ['tenant_id' => $tenant->id, 'codigo' => 'F', 'ejercicio' => null],
                [
                    'tipo' => 'ordinaria',
                    'proximo_numero' => 1,
                    'formato' => '{serie}-{anio}-{numero:0000}',
                    'activa' => true,
                ]
            );
        });
    }

    public static function getCustomColumns(): array
    {
        return [
            'id',
            'nombre_comercial',
            'razon_social',
            'nif',
            'direccion',
            'cp',
            'ciudad',
            'provincia',
            'pais',
            'regimen_impositivo',
            'email',
            'activo',
            'logo_path',
            'logo_mini_path',
            'login_logo_path',
            'login_imagen_path',
            'logo_facturacion_path',
            'favicon_path',
            'created_at',
            'updated_at',
        ];
    }
}
