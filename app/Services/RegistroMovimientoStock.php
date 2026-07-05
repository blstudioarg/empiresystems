<?php

namespace App\Services;

use App\Enums\OrigenMovimientoStock;
use App\Enums\TipoArticulo;
use App\Enums\TipoMovimientoStock;
use App\Exceptions\MovimientoStockInvalidoException;
use App\Models\Articulo;
use App\Models\Compra;
use App\Models\Factura;
use App\Models\MovimientoStock;
use Illuminate\Support\Facades\DB;

/**
 * Único punto de escritura del inventario: calcula `stock_resultante` encadenado y sincroniza
 * `articulos.stock_actual` de forma atómica (lockForUpdate) dentro de una transacción. El ledger
 * `movimientos_stock` es append-only: nunca se actualiza ni se borra un movimiento ya creado.
 */
class RegistroMovimientoStock
{
    public function registrar(
        Articulo $articulo,
        TipoMovimientoStock $tipo,
        float $cantidad,
        OrigenMovimientoStock $origen,
        ?string $motivo = null,
        ?Factura $factura = null,
        ?Compra $compra = null,
    ): MovimientoStock {
        if ($cantidad <= 0) {
            throw new MovimientoStockInvalidoException('La cantidad del movimiento debe ser mayor que cero.');
        }

        return DB::transaction(function () use ($articulo, $tipo, $cantidad, $origen, $motivo, $factura, $compra) {
            /** @var Articulo $articuloBloqueado */
            $articuloBloqueado = Articulo::whereKey($articulo->getKey())->lockForUpdate()->firstOrFail();

            if ($articuloBloqueado->tipo !== TipoArticulo::Producto || ! $articuloBloqueado->gestion_stock) {
                throw new MovimientoStockInvalidoException(
                    'Este artículo no gestiona stock (es un servicio o no tiene gestión de stock activada).'
                );
            }

            $stockAnterior = (float) $articuloBloqueado->stock_actual;

            $stockResultante = match ($tipo) {
                TipoMovimientoStock::Entrada => $stockAnterior + $cantidad,
                TipoMovimientoStock::Salida => $stockAnterior - $cantidad,
                TipoMovimientoStock::Ajuste => $stockAnterior + $cantidad,
            };

            $movimiento = MovimientoStock::create([
                'tenant_id' => $articuloBloqueado->tenant_id,
                'articulo_id' => $articuloBloqueado->id,
                'tipo' => $tipo,
                'cantidad' => $cantidad,
                'stock_resultante' => $stockResultante,
                'origen' => $origen,
                'factura_id' => $factura?->id,
                'compra_id' => $compra?->id,
                'motivo' => $motivo,
                'ocurrido_at' => now(),
            ]);

            $articuloBloqueado->stock_actual = $stockResultante;
            $articuloBloqueado->save();

            return $movimiento;
        });
    }
}
