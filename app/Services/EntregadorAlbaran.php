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
 * Transición `borrador → entregado` (FR-005): mueve stock de salida por cada línea con artículo
 * `producto` + `gestion_stock`, trazado al albarán vía `RegistroMovimientoStock` (research D3), e
 * incrementa `cantidad_entregada` en cada línea de presupuesto referenciada (research D2).
 */
class EntregadorAlbaran
{
    public function __construct(private readonly RegistroMovimientoStock $registroMovimientoStock) {}

    public function entregar(Albaran $albaran, ?string $fechaEntrega = null): Albaran
    {
        return DB::transaction(function () use ($albaran, $fechaEntrega) {
            $bloqueado = Albaran::withoutGlobalScopes()
                ->with('lineas.articulo', 'lineas.presupuestoLinea')
                ->where('id', $albaran->id)
                ->lockForUpdate()
                ->first();

            if ($bloqueado->estado !== EstadoAlbaran::Borrador) {
                throw new AlbaranTransicionInvalidaException('Solo un albarán en borrador se puede confirmar como entregado.');
            }

            foreach ($bloqueado->lineas as $linea) {
                $articulo = $linea->articulo;

                if ($articulo && $articulo->tipo === TipoArticulo::Producto && $articulo->gestion_stock) {
                    $this->registroMovimientoStock->registrar(
                        articulo: $articulo,
                        tipo: TipoMovimientoStock::Salida,
                        cantidad: (float) $linea->cantidad,
                        origen: OrigenMovimientoStock::Albaran,
                        albaran: $bloqueado,
                    );
                }

                if ($linea->presupuestoLinea) {
                    $linea->presupuestoLinea->increment('cantidad_entregada', (float) $linea->cantidad);
                }
            }

            $bloqueado->update([
                'estado' => EstadoAlbaran::Entregado,
                'fecha_entrega' => $fechaEntrega ?? now()->toDateString(),
            ]);

            return $bloqueado->refresh();
        });
    }
}
