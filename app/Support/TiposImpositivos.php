<?php

namespace App\Support;

use App\Enums\RegimenImpositivo;

class TiposImpositivos
{
    /**
     * @return array<int, float>|null
     */
    public static function validosPara(RegimenImpositivo $regimen): ?array
    {
        return match ($regimen) {
            RegimenImpositivo::Iva => [0, 4, 10, 21],
            RegimenImpositivo::Igic => [0, 3, 7, 9.5, 15, 20],
            RegimenImpositivo::Ipsi => null,
        };
    }

    public static function recargoParaTipoIva(float $tipoIva): float
    {
        return match ($tipoIva) {
            21.0 => 5.2,
            10.0 => 1.4,
            4.0 => 0.5,
            default => 0.0,
        };
    }

    public static function esTipoValido(RegimenImpositivo $regimen, float $tipoImpositivo): bool
    {
        $validos = self::validosPara($regimen);

        if ($validos === null) {
            return $tipoImpositivo >= 0 && $tipoImpositivo <= 100;
        }

        return in_array($tipoImpositivo, $validos, true);
    }
}
