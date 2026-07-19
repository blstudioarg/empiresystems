<?php

namespace App\Console\Commands;

use App\Services\AlmacenImportaciones;
use Illuminate\Console\Command;

/**
 * Barre los ficheros de `storage/app/private/importaciones/` de más de 24 h: huérfanos de
 * previsualizaciones que nunca se confirmaron ni cancelaron (RGPD — minimización, Principio II).
 * Mismo patrón que `logs:purgar`/`leads:purgar` (bootstrap/app.php → withSchedule).
 */
class PurgarImportaciones extends Command
{
    protected $signature = 'importaciones:purgar';

    protected $description = 'Elimina los ficheros de importación pendientes de más de 24 horas (RGPD — minimización)';

    public function handle(AlmacenImportaciones $almacen): int
    {
        $borrados = $almacen->purgarHuerfanos();

        if ($borrados > 0) {
            $this->info("{$borrados} ficheros de importación huérfanos purgados.");
        }

        return self::SUCCESS;
    }
}
