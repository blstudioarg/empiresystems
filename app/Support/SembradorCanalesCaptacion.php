<?php

namespace App\Support;

use App\Models\CanalCaptacion;

/**
 * Siembra el conjunto inicial de canales de captación (research D3, feature 033). Se usa (a) al
 * provisionar un tenant nuevo (`SuperAdmin\TenantController::store`) y (b) ya se sembró para los
 * tenants existentes directamente desde la migración `create_canales_captacion_table`.
 */
class SembradorCanalesCaptacion
{
    public const NOMBRES = [
        'Web',
        'Recomendación',
        'Feria/Evento',
        'Campaña de email',
        'Llamada entrante',
        'Redes sociales',
        'Otro',
    ];

    public static function sembrar(int $tenantId): void
    {
        foreach (self::NOMBRES as $orden => $nombre) {
            CanalCaptacion::create([
                'tenant_id' => $tenantId,
                'nombre' => $nombre,
                'activo' => true,
                'orden' => $orden,
            ]);
        }
    }
}
