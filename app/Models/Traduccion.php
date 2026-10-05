<?php

namespace App\Models;

use Database\Factories\TraduccionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Traducción automática de un texto de la aplicación a un idioma (feature 050).
 *
 * **Tabla central, sin `BelongsToTenant`**: son textos de la app («Cobrar»), no datos de ningún
 * tenant; compartirlos hace que cada texto se traduzca una sola vez (research D11). Las
 * correcciones del tenant viven en {@see TraduccionCorreccion} y prevalecen sobre esta fila.
 *
 * El texto se identifica por `hash` (SHA-256 del español exacto, con sus `:variables`): cambiar
 * el texto en el código produce un hash nuevo y, con él, una traducción nueva (FR-011).
 */
class Traduccion extends Model
{
    /** @use HasFactory<TraduccionFactory> */
    use HasFactory;

    public const ESTADO_PENDIENTE = 'pendiente';

    public const ESTADO_TRADUCIDA = 'traducida';

    public const ESTADO_ERROR = 'error';

    protected $table = 'traducciones';

    protected $fillable = [
        'idioma',
        'hash',
        'texto',
        'ambito',
        'es_html',
        'traduccion',
        'estado',
        'intentos',
        'ultimo_error',
        'traducida_en',
        'vista_en',
    ];

    protected function casts(): array
    {
        return [
            'es_html' => 'boolean',
            'intentos' => 'integer',
            'traducida_en' => 'datetime',
            'vista_en' => 'datetime',
        ];
    }

    public static function hashDe(string $texto): string
    {
        return hash('sha256', $texto);
    }

    /** @param  Builder<Traduccion>  $query */
    public function scopeTraducidas(Builder $query): void
    {
        $query->where('estado', self::ESTADO_TRADUCIDA);
    }

    /** @param  Builder<Traduccion>  $query */
    public function scopePendientes(Builder $query): void
    {
        $query->where('estado', self::ESTADO_PENDIENTE);
    }
}
