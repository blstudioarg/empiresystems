<?php

namespace App\Support;

class RetencionLeadsTenant
{
    public const CLAVE_RETENCION_DIAS = ConfigCrm::CLAVE_RETENCION_DIAS;

    public const DEFAULT_RETENCION_DIAS = ConfigCrm::DEFAULT_RETENCION_DIAS;

    public static function dias(int $tenantId): int
    {
        return ConfigCrm::retencionDias($tenantId);
    }
}
