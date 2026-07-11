<?php

namespace App\Services;

use App\Enums\EstadoAlbaran;
use App\Enums\OrigenMovimientoStock;
use App\Enums\TipoArticulo;
use App\Enums\TipoMovimientoStock;
use App\Exceptions\AlbaranTransicionInvalidaException;
use App\Models\Albaran;
use Illuminate\Support\Facades\DB;

/**
 * Transición `entregado → anulado` (FR-006/FR-007): revierte el stock movido al entregar (entrada
 * trazada al albarán, mismo origen semántico que las rectificativas de factura — Devolucion,
 * research D3) y repone `cantidad_entregada` en las líneas de presupuesto de origen.
 */
class AnuladorAlbaran
{
    public function __construct(private readonly RegistroMovimientoStock $registroMovimientoStock) {}

    public function anular(Albaran $albaran): Albaran
    {
        return DB::transaction(function () use ($albaran) {
            $bloqueado = Albaran::withoutGlobalScopes()
                ->with('lineas.articulo', 'lineas.presupuestoLinea')
                ->where('id', $albaran->id)
                ->lockForUpdate()
                ->first();

            if ($bloqueado->estado !== EstadoAlbaran::Entregado) {
                throw new AlbaranTransicionInvalidaException('Solo un albarán entregado y no facturado se puede anular.');
            }

            foreach ($bloqueado->lineas as $linea) {
                $articulo = $linea->articulo;

                if ($articulo && $articulo->tipo === TipoArticulo::Producto && $articulo->gestion_stock) {
                    $this->registroMovimientoStock->registrar(
                        articulo: $articulo,
                        tipo: TipoMovimientoStock::Entrada,
                        cantidad: (float) $linea->cantidad,
                        origen: OrigenMovimientoStock::Devolucion,
                        albaran: $bloqueado,
                    );
                }

                if ($linea->presupuestoLinea) {
                    $linea->presupuestoLinea->decrement('cantidad_entregada', (float) $linea->cantidad);
                }
            }

            $bloqueado->update(['estado' => EstadoAlbaran::Anulado]);

            return $bloqueado->refresh();
        });
    }
}
