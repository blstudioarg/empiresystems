<?php

namespace App\Support;

use App\Enums\RegimenImpositivo;

class TiposImpositivos
{
    /**
     * Devuelve `null` para los tres regímenes: el tipo impositivo es siempre un campo numérico
     * libre (0-100), no una lista cerrada. Los valores anteriores por régimen (IVA 0/4/10/21,
     * IGIC 0/3/7/9.5/15/20) quedan solo como referencia en `tipoPorDefecto()` y en la
     * documentación; no se fuerzan porque un tenant puede necesitar un tipo fuera de esa lista
     * (p. ej. al cambiar de régimen con artículos/facturas ya creados en otro tipo).
     *
     * @return array<int, float>|null
     */
    public static function validosPara(RegimenImpositivo $regimen): ?array
    {
        return null;
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

    /**
     * @return array{value: string, label: string, tiposValidos: array<int, float>|null, tipoPorDefecto: float, aplicaRecargo: bool}
     */
    public static function payloadVista(RegimenImpositivo $regimen): array
    {
        return [
            'value' => $regimen->value,
            'label' => $regimen->label(),
            'tiposValidos' => self::validosPara($regimen),
            'tipoPorDefecto' => $regimen->tipoPorDefecto(),
            'aplicaRecargo' => $regimen === RegimenImpositivo::Iva,
        ];
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
