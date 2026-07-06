<?php

namespace App\Console\Commands;

use App\Models\Fichaje;
use App\Models\Tenant;
use App\Support\RetencionGeoTenant;
use Illuminate\Console\Command;

class PurgarGeoFichajes extends Command
{
    protected $signature = 'fichajes:purgar-geo';

    protected $description = 'Nulifica las columnas de geo (resultado_ubicacion, distancia_metros, precision_metros) de los fichajes más antiguos que el plazo de retención de cada tenant (RGPD — minimización); la fila de jornada se conserva siempre (D5)';

    private const TAMANO_LOTE = 500;

    public function handle(): int
    {
        Tenant::query()->each(function (Tenant $tenant) {
            $dias = RetencionGeoTenant::dias($tenant->id);
            $limite = now()->subDays($dias);

            $total = 0;

            do {
                $actualizados = Fichaje::withoutGlobalScopes()
                    ->where('tenant_id', $tenant->id)
                    ->where('ocurrido_at', '<', $limite)
                    ->where(function ($query) {
                        $query->whereNotNull('resultado_ubicacion')
                            ->orWhereNotNull('distancia_metros')
                            ->orWhereNotNull('precision_metros');
                    })
                    ->limit(self::TAMANO_LOTE)
                    ->update([
                        'resultado_ubicacion' => null,
                        'distancia_metros' => null,
                        'precision_metros' => null,
                    ]);

                $total += $actualizados;
            } while ($actualizados === self::TAMANO_LOTE);

            if ($total > 0) {
                $this->info("Tenant {$tenant->id}: {$total} fichajes purgados de geo (retención {$dias} días).");
            }
        });

        return self::SUCCESS;
    }
}
