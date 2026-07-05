<?php

namespace App\Console\Commands;

use App\Models\LogActividad;
use App\Models\Tenant;
use App\Support\RetencionLogsTenant;
use Illuminate\Console\Command;

class PurgarLogsActividad extends Command
{
    protected $signature = 'logs:purgar';

    protected $description = 'Elimina por lotes las filas de logs_actividad más antiguas que el plazo de retención de cada tenant (RGPD — minimización)';

    private const TAMANO_LOTE = 500;

    public function handle(): int
    {
        Tenant::query()->each(function (Tenant $tenant) {
            $dias = RetencionLogsTenant::dias($tenant->id);
            $limite = now()->subDays($dias);

            $total = 0;

            do {
                $borrados = LogActividad::withoutGlobalScopes()
                    ->where('tenant_id', $tenant->id)
                    ->where('ocurrido_at', '<', $limite)
                    ->limit(self::TAMANO_LOTE)
                    ->delete();

                $total += $borrados;
            } while ($borrados === self::TAMANO_LOTE);

            if ($total > 0) {
                $this->info("Tenant {$tenant->id}: {$total} filas purgadas (retención {$dias} días).");
            }
        });

        return self::SUCCESS;
    }
}
