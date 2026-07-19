<?php

namespace App\Console\Commands;

use App\Enums\VerifactuEstado;
use App\Jobs\RemitirRegistroVerifactu;
use App\Models\Factura;
use App\Models\FacturaEvento;
use Illuminate\Console\Command;

/**
 * Reintento automático de envíos Verifactu en error (research R6). Reencola las facturas en
 * `verifactu_estado = error` de todos los tenants; con tope de intentos automáticos para no
 * insistir indefinidamente sobre un rechazo funcional (que no se arregla reintentando, solo un
 * fallo de transporte lo hace). El reintento manual (`VerifactuController`) no tiene este tope.
 */
class VerifactuReintentar extends Command
{
    protected $signature = 'verifactu:reintentar';

    protected $description = 'Reencola el envío a la AEAT de las facturas Verifactu en error, con tope de intentos automáticos';

    private const TOPE_INTENTOS_AUTOMATICOS = 5;

    public function handle(): int
    {
        $total = 0;

        Factura::withoutGlobalScopes()
            ->where('verifactu_estado', VerifactuEstado::Error->value)
            ->whereNotNull('huella')
            ->chunkById(200, function ($facturas) use (&$total) {
                foreach ($facturas as $factura) {
                    $intentos = FacturaEvento::withoutGlobalScopes()
                        ->where('factura_id', $factura->id)
                        ->where('tipo_evento', 'verifactu_error')
                        ->count();

                    if ($intentos >= self::TOPE_INTENTOS_AUTOMATICOS) {
                        continue;
                    }

                    RemitirRegistroVerifactu::dispatch($factura->id);
                    $total++;
                }
            });

        $this->info("{$total} facturas reencoladas para reintento de envío Verifactu.");

        return self::SUCCESS;
    }
}
