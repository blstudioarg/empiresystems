<?php

namespace App\Models;

use Database\Factories\ArchivoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class Archivo extends Model
{
    /** @use HasFactory<ArchivoFactory> */
    use BelongsToTenant, HasFactory, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'carpeta_id',
        'nombre',
        'nombre_original',
        'ruta',
        'mime',
        'extension',
        'tamano',
        'subido_por',
    ];

    protected function casts(): array
    {
        return [
            'tamano' => 'integer',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function carpeta(): BelongsTo
    {
        return $this->belongsTo(Carpeta::class, 'carpeta_id');
    }

    public function subidoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'subido_por');
    }
}
