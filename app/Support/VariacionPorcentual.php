<?php

namespace App\Support;

class VariacionPorcentual
{
    public static function calcular(float|int $actual, float|int $anterior): ?float
    {
        if ((float) $anterior === 0.0) {
            return null;
        }

        return round((($actual - $anterior) / $anterior) * 100, 2);
    }
}
