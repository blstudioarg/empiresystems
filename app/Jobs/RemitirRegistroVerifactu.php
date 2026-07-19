<?php

namespace App\Jobs;

use App\Enums\VerifactuEstado;
use App\Models\Factura;
use App\Models\FacturaEvento;
use App\Services\RemisorVerifactu;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Envío asíncrono del registro Verifactu ya sellado (contracts/aeat-webservice.md). Despachado con
 * `DB::afterCommit()` desde `EmisorFacturas::emitir()` y desde `RegistroVerifactu::registrarAnulacion()`
 * — solo se envía lo que ya quedó confirmado en base de datos (research R3). Un intento por
 * despacho: el reintento lo gestionan el comando programado y la acción manual, no el backoff de
 * la cola (research R6), para no insistir automáticamente sobre un rechazo funcional.
 */
class RemitirRegistroVerifactu implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(
        public readonly int $facturaId,
        public readonly string $tipoOperacion = 'alta',
    ) {}

    public function handle(RemisorVerifactu $remisor): void
    {
        $factura = Factura::withoutGlobalScopes()->find($this->facturaId);

        if ($factura === null) {
            return;
        }

        if ($this->tipoOperacion === 'anulacion') {
            $this->remitirAnulacion($factura, $remisor);

            return;
        }

        $this->remitirAlta($factura, $remisor);
    }

    private function remitirAlta(Factura $factura, RemisorVerifactu $remisor): void
    {
        if (! $factura->tieneRegistroVerifactu()) {
            return;
        }

        $resultado = $remisor->remitir($factura);

        $factura->verifactu_estado = $resultado->estado;
        $factura->save();

        $this->registrarEvento($factura, $resultado);
    }

    private function remitirAnulacion(Factura $factura, RemisorVerifactu $remisor): void
    {
        $evento = FacturaEvento::withoutGlobalScopes()
            ->where('factura_id', $factura->id)
            ->where('tipo_evento', 'verifactu_anulacion')
            ->orderByDesc('id')
            ->first();

        if ($evento === null) {
            return;
        }

        $resultado = $remisor->remitirAnulacion($factura, $evento->detalle['xml']);

        // La anulación no tiene un `verifactu_estado` propio en `facturas` (ese campo refleja el
        // alta); el resultado del envío de la anulación solo queda en el evento.
        $this->registrarEvento($factura, $resultado);
    }

    private function registrarEvento(Factura $factura, \App\Services\ResultadoRemisionVerifactu $resultado): void
    {
        FacturaEvento::create([
            'tenant_id' => $factura->tenant_id,
            'factura_id' => $factura->id,
            'tipo_evento' => $resultado->estado === VerifactuEstado::Enviada ? 'verifactu_enviado' : 'verifactu_error',
            'detalle' => [
                'codigo' => $resultado->codigo,
                'descripcion' => $resultado->descripcion,
                'csv' => $resultado->csv,
                'tipo' => $resultado->tipoError,
                'operacion' => $this->tipoOperacion,
            ],
            'ocurrido_at' => now(),
        ]);
    }
}
