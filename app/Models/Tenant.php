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
            // Series por defecto del tenant: una por cada tipo de factura soportado (ordinaria,
            // rectificativa, simplificada). Antes solo se sembraba "F" aquí y "R"/"S" dependían de
            // `SerieSeeder`, que solo corre una vez y solo para el tenant demo — cualquier tenant
            // nuevo se quedaba sin serie rectificativa/simplificada y `Serie::activaPorTipo()`
            // (firstOrFail) reventaba al primer intento de rectificar o emitir un ticket POS.
            foreach (self::seriesPorDefecto() as $codigo => $atributos) {
                Serie::firstOrCreate(
                    ['tenant_id' => $tenant->id, 'codigo' => $codigo, 'ejercicio' => null],
                    $atributos
                );
            }
        });
    }

    /**
     * Catálogo de series que todo tenant nuevo debe tener desde el alta. Único punto de verdad
     * para el sembrado automático (`booted()`) y para seeders/factories que necesiten los mismos
     * valores por defecto.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function seriesPorDefecto(): array
    {
        return [
            'F' => [
                'tipo' => 'ordinaria',
                'proximo_numero' => 1,
                'formato' => '{serie}-{anio}-{numero:0000}',
                'activa' => true,
            ],
            'R' => [
                'tipo' => 'rectificativa',
                'proximo_numero' => 1,
                'formato' => '{serie}-{anio}-{numero:0000}',
                'activa' => true,
            ],
            'S' => [
                'tipo' => 'simplificada',
                'proximo_numero' => 1,
                'formato' => '{serie}-{anio}-{numero:0000}',
                'activa' => true,
            ],
        ];
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
