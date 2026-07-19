<?php

namespace App\Services;

use App\Enums\VerifactuEstado;

/**
 * Resultado de una remisión al web service de la AEAT (contracts/aeat-webservice.md).
 */
class ResultadoRemisionVerifactu
{
    public function __construct(
        public readonly VerifactuEstado $estado,
        public readonly ?string $codigo = null,
        public readonly ?string $descripcion = null,
        public readonly ?string $csv = null,
        public readonly ?string $tipoError = null,
    ) {}

    public static function enviada(?string $csv = null): self
    {
        return new self(VerifactuEstado::Enviada, csv: $csv);
    }

    public static function rechazo(?string $codigo, ?string $descripcion): self
    {
        return new self(VerifactuEstado::Error, codigo: $codigo, descripcion: $descripcion, tipoError: 'rechazo');
    }

    public static function errorTransporte(string $descripcion): self
    {
        return new self(VerifactuEstado::Error, descripcion: $descripcion, tipoError: 'transporte');
    }
}
