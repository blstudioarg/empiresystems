<?php

namespace App\Console\Commands;

use App\Models\MiembroEquipo;
use App\Models\Tenant;
use App\Support\RetencionMiembroTenant;
use Illuminate\Console\Command;

class PurgarCasaMiembros extends Command
{
    protected $signature = 'miembros:purgar-casa';

    protected $description = 'Nulifica los datos de casa (casa_direccion, casa_latitud, casa_longitud) de los miembros dados de baja hace más del plazo de retención de cada tenant (RGPD — minimización, D12); el miembro, su distancia_casa_trabajo_metros y sus fichajes se conservan';

    private const TAMANO_LOTE = 500;

    public function handle(): int
    {
        Tenant::query()->each(function (Tenant $tenant) {
            $dias = RetencionMiembroTenant::dias($tenant->id);
            $limite = now()->subDays($dias);

            $total = 0;

            do {
                $actualizados = MiembroEquipo::withoutGlobalScopes()
                    ->where('tenant_id', $tenant->id)
                    ->whereNotNull('dado_baja_at')
                    ->where('dado_baja_at', '<', $limite)
                    ->where(function ($query) {
                        $query->whereNotNull('casa_direccion')
                            ->orWhereNotNull('casa_latitud')
                            ->orWhereNotNull('casa_longitud');
                    })
                    ->limit(self::TAMANO_LOTE)
                    ->update([
                        'casa_direccion' => null,
                        'casa_latitud' => null,
                        'casa_longitud' => null,
                    ]);

                $total += $actualizados;
            } while ($actualizados === self::TAMANO_LOTE);

            if ($total > 0) {
                $this->info("Tenant {$tenant->id}: {$total} miembros purgados de datos de casa (retención {$dias} días).");
            }
        });

        return self::SUCCESS;
    }
}
