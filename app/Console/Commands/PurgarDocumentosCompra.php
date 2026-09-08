<?php

namespace App\Console\Commands;

use App\Services\AlmacenDocumentosCompra;
use Illuminate\Console\Command;

/**
 * Barre los documentos de `storage/app/private/compras-documentos/` caducados: huérfanos de
 * propuestas que nunca se confirmaron ni descartaron (RGPD — minimización, Principio II).
 * Mismo patrón que `importaciones:purgar` (bootstrap/app.php → withSchedule).
 */
class PurgarDocumentosCompra extends Command
{
    protected $signature = 'compras-documentos:purgar';

    protected $description = 'Elimina los documentos de compra pendientes caducados (RGPD — minimización)';

    public function handle(AlmacenDocumentosCompra $almacen): int
    {
        $borrados = $almacen->purgarHuerfanos();

        if ($borrados > 0) {
            $this->info("{$borrados} documentos de compra huérfanos purgados.");
        }

        return self::SUCCESS;
    }
}
