<?php

namespace App\Console\Commands;

use App\Models\AsistenteConversacion;
use App\Models\AsistenteMensaje;
use App\Models\Tenant;
use App\Support\RetencionAsistenteTenant;
use Illuminate\Console\Command;

/**
 * Retención de las conversaciones del asistente (feature 045, RGPD — minimización, Principio II).
 *
 * Mismo patrón que `PurgarLeads` / `PurgarLogsActividad`: recorrido por tenant, plazo configurable,
 * borrado en lotes. La constitución obliga a reutilizar este patrón en vez de inventar uno nuevo por
 * feature.
 *
 * **Se purga por `ultima_actividad_en`, no por `created_at`**, a diferencia de `PurgarLeads`: un
 * lead descartado no vuelve a usarse, pero una conversación empezada hace meses y usada ayer está
 * viva y no debe desaparecer.
 */
class PurgarConversacionesAsistente extends Command
{
    protected $signature = 'asistente:purgar';

    protected $description = 'Elimina definitivamente las conversaciones del asistente sin actividad más antiguas que el plazo de retención de cada tenant (RGPD — minimización)';

    private const TAMANO_LOTE = 500;

    public function handle(): int
    {
        Tenant::query()->each(function (Tenant $tenant) {
            $dias = RetencionAsistenteTenant::dias($tenant->id);
            $limite = now()->subDays($dias);

            $total = 0;

            do {
                $ids = AsistenteConversacion::withoutGlobalScopes()
                    ->where('tenant_id', $tenant->id)
                    ->where('ultima_actividad_en', '<', $limite)
                    ->limit(self::TAMANO_LOTE)
                    ->pluck('id');

                if ($ids->isEmpty()) {
                    break;
                }

                // Los mensajes se borran explícitamente además de por la FK en cascada: un borrado
                // por lotes con `whereIn` no dispara la cascada en todos los motores, y aquí no
                // podemos permitirnos dejar mensajes huérfanos con datos personales dentro.
                AsistenteMensaje::withoutGlobalScopes()->whereIn('conversacion_id', $ids)->delete();
                AsistenteConversacion::withoutGlobalScopes()->whereIn('id', $ids)->delete();

                $total += $ids->count();
            } while ($ids->count() === self::TAMANO_LOTE);

            if ($total > 0) {
                $this->info("Tenant {$tenant->id}: {$total} conversaciones del asistente purgadas (retención {$dias} días).");
            }
        });

        return self::SUCCESS;
    }
}
