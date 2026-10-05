<?php

namespace App\Services;

use App\Enums\AccionLogActividad;
use App\Enums\EntidadLogActividad;
use App\Enums\EstadoFactura;
use App\Enums\OrigenMovimientoStock;
use App\Enums\TipoMovimientoStock;
use App\Exceptions\FacturaNoAnulableException;
use App\Models\Factura;
use App\Models\FacturaEvento;
use App\Models\MovimientoStock;
use App\Models\User;
use App\Support\VerifactuTenant;
use Illuminate\Support\Facades\DB;

/**
 * Anulación de una factura emitida (facturas ordinarias y tickets del POS, feature 051).
 *
 * Restringida al caso de un registro erróneo que aún no produjo efectos económicos aparte: sin
 * cobros registrados en `pagos` y sin rectificativa. La corrección ordinaria de una factura cobrada
 * o devuelta sigue siendo la rectificativa. Nunca se borra ni se renumera nada (Principio II): solo
 * cambia el estado, queda el evento con el motivo y, con Verifactu, el registro de anulación.
 *
 * Los tickets además devuelven al stock lo que descontaron (`$revertirStock`): una entrada por cada
 * salida registrada con el ticket (líneas y opciones vinculadas). El ledger es append-only, así que
 * la salida original no se toca.
 */
class AnuladorFactura
{
    public function __construct(
        private readonly RegistroVerifactu $registroVerifactu,
        private readonly RegistroMovimientoStock $registroMovimientoStock,
        private readonly RegistradorActividad $registradorActividad,
    ) {}

    /**
     * @throws FacturaNoAnulableException
     */
    public function anular(Factura $factura, string $motivo, User $usuario, bool $revertirStock = false): Factura
    {
        $factura = DB::transaction(function () use ($factura, $motivo, $revertirStock) {
            // Bloqueo de fila: de dos anulaciones simultáneas, la segunda ve `anulada` y falla.
            /** @var Factura $bloqueada */
            $bloqueada = Factura::query()->whereKey($factura->getKey())->lockForUpdate()->firstOrFail();

            $this->comprobarAnulable($bloqueada);

            $bloqueada->estado = EstadoFactura::Anulada;
            $bloqueada->save();

            FacturaEvento::create([
                'tenant_id' => $bloqueada->tenant_id,
                'factura_id' => $bloqueada->id,
                'tipo_evento' => 'anulada',
                'detalle' => ['motivo' => $motivo],
                'ocurrido_at' => now(),
            ]);

            if (VerifactuTenant::activo($bloqueada->tenant_id) && $bloqueada->tieneRegistroVerifactu()) {
                $this->registroVerifactu->registrarAnulacion($bloqueada, $motivo);
            }

            if ($revertirStock) {
                $this->revertirStock($bloqueada);
            }

            return $bloqueada;
        });

        $this->registradorActividad->registrar(
            $usuario,
            AccionLogActividad::Modificacion,
            EntidadLogActividad::Factura,
            $factura->id,
            "Anuló la factura {$factura->numero_completo}",
        );

        return $factura;
    }

    /** @throws FacturaNoAnulableException */
    private function comprobarAnulable(Factura $factura): void
    {
        if ($factura->estado !== EstadoFactura::Emitida) {
            throw new FacturaNoAnulableException(FacturaNoAnulableException::NO_EMITIDA);
        }

        if ($factura->montoCobrado() > 0) {
            throw new FacturaNoAnulableException(FacturaNoAnulableException::CON_COBROS);
        }

        if ($factura->rectificativa()->exists()) {
            throw new FacturaNoAnulableException(FacturaNoAnulableException::RECTIFICADA);
        }
    }

    private function revertirStock(Factura $factura): void
    {
        $salidas = MovimientoStock::query()
            ->with('articulo')
            ->where('factura_id', $factura->id)
            ->where('tipo', TipoMovimientoStock::Salida)
            ->get();

        foreach ($salidas as $salida) {
            $this->registroMovimientoStock->registrar(
                articulo: $salida->articulo,
                tipo: TipoMovimientoStock::Entrada,
                cantidad: (float) $salida->cantidad,
                origen: OrigenMovimientoStock::Devolucion,
                motivo: "Anulación del ticket {$factura->numero_completo}",
                factura: $factura,
            );
        }
    }
}
