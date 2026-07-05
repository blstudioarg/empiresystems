<?php

namespace App\Services;

use App\Enums\EstadoCompra;
use App\Enums\OrigenMovimientoStock;
use App\Enums\TipoArticulo;
use App\Enums\TipoMovimientoStock;
use App\Exceptions\CompraNoModificableException;
use App\Models\Compra;
use Illuminate\Support\Facades\DB;

/**
 * Confirma/anula compras. `confirmar()` genera entradas de stock por cada línea con artículo
 * producto+gestión (las líneas libres solo aportan a los totales); `anular()` revierte esas
 * entradas con movimientos de salida. Nunca escribe stock directamente: delega en
 * RegistroMovimientoStock, el único punto de escritura del inventario.
 */
class RegistroCompra
{
    public function __construct(private readonly RegistroMovimientoStock $registroMovimientoStock) {}

    public function confirmar(Compra $compra): Compra
    {
        if ($compra->estado !== EstadoCompra::Borrador) {
            throw new CompraNoModificableException('Solo se pueden confirmar compras en borrador.');
        }

        return DB::transaction(function () use ($compra) {
            foreach ($compra->lineas as $linea) {
                $articulo = $linea->articulo;

                if ($articulo && $articulo->tipo === TipoArticulo::Producto && $articulo->gestion_stock) {
                    $this->registroMovimientoStock->registrar(
                        articulo: $articulo,
                        tipo: TipoMovimientoStock::Entrada,
                        cantidad: (float) $linea->cantidad,
                        origen: OrigenMovimientoStock::Compra,
                        compra: $compra,
                    );
                }
            }

            $compra->estado = EstadoCompra::Confirmada;
            $compra->confirmada_at = now();
            $compra->save();

            return $compra->refresh();
        });
    }

    public function anular(Compra $compra): Compra
    {
        if ($compra->estado !== EstadoCompra::Confirmada) {
            throw new CompraNoModificableException('Solo se pueden anular compras confirmadas.');
        }

        return DB::transaction(function () use ($compra) {
            foreach ($compra->lineas as $linea) {
                $articulo = $linea->articulo;

                if ($articulo && $articulo->tipo === TipoArticulo::Producto && $articulo->gestion_stock) {
                    $this->registroMovimientoStock->registrar(
                        articulo: $articulo,
                        tipo: TipoMovimientoStock::Salida,
                        cantidad: (float) $linea->cantidad,
                        origen: OrigenMovimientoStock::Compra,
                        compra: $compra,
                    );
                }
            }

            $compra->estado = EstadoCompra::Anulada;
            $compra->anulada_at = now();
            $compra->save();

            return $compra->refresh();
        });
    }
}
