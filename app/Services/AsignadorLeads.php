<?php

namespace App\Services;

use App\Enums\EstrategiaAsignacion;
use App\Models\Configuracion;
use App\Support\ConfigCrm;
use Illuminate\Support\Facades\DB;

/**
 * Único punto de asignación automática de leads (research D3). La estrategia `manual` no asigna
 * nada aquí (el responsable lo elige el usuario en el alta); `round_robin` reparte de forma
 * equitativa manteniendo un puntero por tenant en `configuraciones`, bajo bloqueo transaccional
 * (mismo patrón que `NumeradorFacturas`) para que una importación concurrente no repita comercial.
 */
class AsignadorLeads
{
    public function asignar(int $tenantId): ?int
    {
        if (ConfigCrm::estrategiaAsignacion($tenantId) !== EstrategiaAsignacion::RoundRobin) {
            return null;
        }

        $comerciales = ConfigCrm::comercialesAsignacion($tenantId);

        if (empty($comerciales)) {
            return null;
        }

        return DB::transaction(function () use ($tenantId, $comerciales) {
            $config = Configuracion::query()
                ->where('tenant_id', $tenantId)
                ->where('clave', ConfigCrm::CLAVE_ASIGNACION_ULTIMO_INDICE)
                ->lockForUpdate()
                ->first();

            $indiceActual = $config ? (int) $config->valor : 0;
            $comercialId = $comerciales[$indiceActual % count($comerciales)];
            $siguienteIndice = (string) ($indiceActual + 1);

            if ($config) {
                $config->update(['valor' => $siguienteIndice]);
            } else {
                Configuracion::create([
                    'tenant_id' => $tenantId,
                    'clave' => ConfigCrm::CLAVE_ASIGNACION_ULTIMO_INDICE,
                    'valor' => $siguienteIndice,
                    'tipo' => 'integer',
                    'grupo' => 'crm',
                    'descripcion' => null,
                ]);
            }

            return $comercialId;
        });
    }
}
