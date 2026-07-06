<?php

namespace App\Console\Commands;

use App\Models\Compra;
use App\Models\Factura;
use App\Models\Tenant;
use App\Services\GeneradorFacturae;
use App\Support\RetencionLogsTenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Purga los XML Facturae (emitidos y recibidos) más antiguos que el plazo de retención de cada
 * tenant, para no conservar datos personales indefinidamente (Principio II, FR-024). Reutiliza
 * `App\Support\RetencionLogsTenant` (mismo mecanismo que `logs:purgar`, no uno nuevo). Solo borra
 * el archivo conservado: la factura/compra y sus importes permanecen; el Facturae emitido se
 * regenera y refirma bajo demanda si se vuelve a solicitar.
 */
class PurgarFacturaeAntiguos extends Command
{
    protected $signature = 'facturae:purgar';

    protected $description = 'Elimina los XML Facturae emitidos/recibidos más antiguos que el plazo de retención de cada tenant';

    private const TAMANO_LOTE = 200;

    public function __construct(private readonly GeneradorFacturae $generador)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        Tenant::query()->each(function (Tenant $tenant) {
            $dias = RetencionLogsTenant::dias($tenant->id);
            $limite = now()->subDays($dias);
            $total = 0;

            Factura::withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)
                ->where('fecha_expedicion', '<', $limite)
                ->whereNotNull('numero')
                ->chunkById(self::TAMANO_LOTE, function ($facturas) use (&$total) {
                    foreach ($facturas as $factura) {
                        $ruta = $this->generador->rutaArchivo($factura);

                        if (Storage::disk('documentos')->exists($ruta)) {
                            Storage::disk('documentos')->delete($ruta);
                            $total++;
                        }
                    }
                });

            Compra::withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)
                ->where('origen', 'facturae')
                ->whereNotNull('archivo_recibido_path')
                ->where('fecha', '<', $limite)
                ->chunkById(self::TAMANO_LOTE, function ($compras) use (&$total) {
                    foreach ($compras as $compra) {
                        if (Storage::disk('documentos')->exists($compra->archivo_recibido_path)) {
                            Storage::disk('documentos')->delete($compra->archivo_recibido_path);
                        }

                        $compra->update(['archivo_recibido_path' => null]);
                        $total++;
                    }
                });

            if ($total > 0) {
                $this->info("Tenant {$tenant->id}: {$total} archivos Facturae purgados (retención {$dias} días).");
            }
        });

        return self::SUCCESS;
    }
}
