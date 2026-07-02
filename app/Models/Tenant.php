<?php

namespace App\Models;

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
        'email',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }

    public static function getCustomColumns(): array
    {
        return [
            'id',
            'nombre_comercial',
            'razon_social',
            'nif',
            'email',
            'activo',
            'created_at',
            'updated_at',
        ];
    }
}
