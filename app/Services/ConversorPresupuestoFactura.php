<?php

namespace App\Services;

use App\Enums\EstadoFactura;
use App\Enums\EstadoPresupuesto;
use App\Enums\FormaPago;
use App\Enums\TipoFactura;
use App\Exceptions\PresupuestoNoConvertibleException;
use App\Models\Factura;
use App\Models\Presupuesto;
use App\Models\Serie;
use Illuminate\Support\Facades\DB;

/**
 * Presupuesto aceptado → Factura en estado `borrador` con líneas e importes congelados
 * (research D4). No consume numeración de serie ni Verifactu: eso ocurre al emitir la factura con
 * el flujo existente (`EmisorFacturas`). Guarda transaccional contra doble facturación (SC-005).
 */
class ConversorPresupuestoFactura
{
    public function convertir(Presupuesto $presupuesto): Factura
    {
        return DB::transaction(function () use ($presupuesto) {
            $bloqueado = Presupuesto::withoutGlobalScopes()
                ->where('id', $presupuesto->id)
                ->lockForUpdate()
                ->first();

            if ($bloqueado->estado !== EstadoPresupuesto::Aceptado) {
                throw new PresupuestoNoConvertibleException(
                    'Solo un presupuesto aceptado se puede convertir a factura.'
                );
            }

            $serie = Serie::activaPorTipo(TipoFactura::Ordinaria);

            $factura = Factura::create([
                'tenant_id' => $presupuesto->tenant_id,
                'serie_id' => $serie->id,
                'numero' => null,
                'numero_completo' => null,
                'tipo' => TipoFactura::Ordinaria,
                'estado' => EstadoFactura::Borrador,
                'cliente_id' => $presupuesto->cliente_id,
                'cliente_nombre' => $presupuesto->receptor_nombre,
                'cliente_razon_social' => $presupuesto->cliente?->razon_social,
                'cliente_nif' => $presupuesto->receptor_nif,
                'cliente_direccion' => $presupuesto->receptor_direccion,
                'cliente_cp' => $presupuesto->receptor_cp,
                'cliente_ciudad' => $presupuesto->receptor_ciudad,
                'cliente_provincia' => $presupuesto->receptor_provincia,
                'cliente_pais' => $presupuesto->receptor_pais,
                'fecha_expedicion' => now()->toDateString(),
                'forma_pago' => FormaPago::Transferencia,
                'moneda' => 'EUR',
                'regimen_impositivo' => $presupuesto->regimen_impositivo,
                'aplica_recargo' => $presupuesto->aplica_recargo,
                'base_total' => $presupuesto->base_total,
                'cuota_impuesto_total' => $presupuesto->cuota_impuesto_total,
                'cuota_recargo_total' => $presupuesto->cuota_recargo_total,
                'irpf_porcentaje' => $presupuesto->irpf_porcentaje,
                'irpf_cuota' => $presupuesto->irpf_cuota,
                'total' => $presupuesto->total,
                'notas' => $presupuesto->notas,
            ]);

            foreach ($presupuesto->lineas as $orden => $linea) {
                $factura->lineas()->create([
                    'tenant_id' => $presupuesto->tenant_id,
                    'articulo_id' => $linea->articulo_id,
                    'concepto' => $linea->concepto,
                    'unidad' => $linea->unidad,
                    'cantidad' => $linea->cantidad,
                    'precio_unitario' => $linea->precio_unitario,
                    'descuento_porcentaje' => $linea->descuento_porcentaje,
                    'base' => $linea->base,
                    'tipo_impositivo' => $linea->tipo_impositivo,
                    'cuota_impuesto' => $linea->cuota_impuesto,
                    'tipo_recargo' => $linea->tipo_recargo,
                    'cuota_recargo' => $linea->cuota_recargo,
                    'orden' => $orden,
                ]);
            }

            $agrupados = $presupuesto->lineas->groupBy(fn ($l) => $presupuesto->regimen_impositivo->value.'|'.$l->tipo_impositivo);
            foreach ($agrupados as $grupo) {
                $factura->impuestos()->create([
                    'tenant_id' => $presupuesto->tenant_id,
                    'tipo_impuesto' => $presupuesto->regimen_impositivo->value,
                    'porcentaje' => $grupo->first()->tipo_impositivo,
                    'base_imponible' => $grupo->sum('base'),
                    'cuota' => $grupo->sum('cuota_impuesto'),
                ]);
            }

            $bloqueado->update([
                'estado' => EstadoPresupuesto::Facturado,
                'convertido_a_factura_id' => $factura->id,
            ]);

            return $factura;
        });
    }
}
