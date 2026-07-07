<?php

namespace App\Models;

use Spatie\Permission\Models\Role;

/**
 * Extiende el Role de spatie solo para castear la columna propia `es_defecto` (feature 027,
 * D10) a boolean; sin cambios de comportamiento respecto al Role estándar.
 */
class Rol extends Role
{
    protected function casts(): array
    {
        return array_merge(parent::casts(), [
            'es_defecto' => 'boolean',
        ]);
    }
}
