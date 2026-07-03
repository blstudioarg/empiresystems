<?php

namespace App\Models;

use App\Enums\RegimenImpositivo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
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
        'regimen_impositivo',
        'email',
        'activo',
        'logo_path',
        'logo_mini_path',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
            'regimen_impositivo' => RegimenImpositivo::class,
        ];
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
            'regimen_impositivo',
            'email',
            'activo',
            'logo_path',
            'logo_mini_path',
            'created_at',
            'updated_at',
        ];
    }
}
