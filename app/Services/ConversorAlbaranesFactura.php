<?php

namespace App\Services;

use App\Enums\EstadoAlbaran;
use App\Enums\EstadoFactura;
use App\Enums\FormaPago;
use App\Enums\TipoFactura;
use App\Exceptions\ConversionAlbaranesException;
use App\Models\Albaran;
use App\Models\Factura;
use App\Models\Serie;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * N albaranes entregados del mismo cliente → una única Factura en estado `borrador` (research D4).
 * No mueve stock (ya se movió en `EntregadorAlbaran` al entregar cada uno): el guard que lo evita
 * vive en `EmisorFacturas::moverStock()` vía `Factura::albaranes()`. Guarda transaccional con
 * bloqueo de fila contra doble facturación (SC-004).
 */
class ConversorAlbaranesFactura
{
    public function convertir(Collection $albaranes): Factura
    {
        if ($albaranes->isEmpty()) {
            throw new ConversionAlbaranesException('Debes seleccionar al menos un albarán.');
        }

        return DB::transaction(function () use ($albaranes) {
            $ids = $albaranes->pluck('id')->all();

            $bloqueados = Albaran::withoutGlobalScopes()
                ->with('lineas')
                ->whereIn('id', $ids)
                ->lockForUpdate()
                ->get();

            if ($bloqueados->count() !== count($ids)) {
                throw new ConversionAlbaranesException('Alguno de los albaranes seleccionados no existe.');
            }

            $noConvertibles = $bloqueados->filter(
                fn (Albaran $albaran) => $albaran->estado !== EstadoAlbaran::Entregado || $albaran->convertido_a_factura_id !== null
            );

            if ($noConvertibles->isNotEmpty()) {
                throw new ConversionAlbaranesException(
                    'Solo se pueden convertir albaranes entregados y no facturados: '.$noConvertibles->pluck('numero')->implode(', ')
                );
            }

            if ($bloqueados->pluck('cliente_id')->unique()->count() > 1) {
                throw new ConversionAlbaranesException('Los albaranes seleccionados deben ser del mismo cliente.');
            }

            if ($bloqueados->pluck('regimen_impositivo')->map(fn ($r) => $r->value)->unique()->count() > 1) {
                throw new ConversionAlbaranesException('Los albaranes seleccionados tienen regímenes impositivos incompatibles.');
            }

            $primero = $bloqueados->first();
            $serie = Serie::activaPorTipo(TipoFactura::Ordinaria);

            $baseTotal = $bloqueados->sum(fn (Albaran $a) => (float) $a->base_total);
            $cuotaImpuestoTotal = $bloqueados->sum(fn (Albaran $a) => (float) $a->cuota_impuesto_total);
            $cuotaRecargoTotal = $bloqueados->sum(fn (Albaran $a) => (float) $a->cuota_recargo_total);
            $total = $bloqueados->sum(fn (Albaran $a) => (float) $a->total);

            $factura = Factura::create([
                'tenant_id' => $primero->tenant_id,
                'serie_id' => $serie->id,
                'numero' => null,
                'numero_completo' => null,
                'tipo' => TipoFactura::Ordinaria,
                'estado' => EstadoFactura::Borrador,
                'cliente_id' => $primero->cliente_id,
                'cliente_nombre' => $primero->receptor_nombre,
                'cliente_razon_social' => $primero->cliente?->razon_social,
                'cliente_nif' => $primero->receptor_nif,
                'cliente_direccion' => $primero->receptor_direccion,
                'cliente_cp' => $primero->receptor_cp,
                'cliente_ciudad' => $primero->receptor_ciudad,
                'cliente_provincia' => $primero->receptor_provincia,
                'cliente_pais' => $primero->receptor_pais,
                'fecha_expedicion' => now()->toDateString(),
                'forma_pago' => FormaPago::Transferencia,
                'moneda' => 'EUR',
                'regimen_impositivo' => $primero->regimen_impositivo,
                'aplica_recargo' => $primero->aplica_recargo,
                'base_total' => round($baseTotal, 2),
                'cuota_impuesto_total' => round($cuotaImpuestoTotal, 2),
                'cuota_recargo_total' => round($cuotaRecargoTotal, 2),
                'irpf_porcentaje' => null,
                'irpf_cuota' => 0,
                'total' => round($total, 2),
                'notas' => null,
            ]);

            $orden = 0;
            $lineasPorImpuesto = [];

            foreach ($bloqueados as $albaran) {
                foreach ($albaran->lineas as $linea) {
                    $factura->lineas()->create([
                        'tenant_id' => $primero->tenant_id,
                        'albaran_id' => $albaran->id,
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
                        'orden' => $orden++,
                    ]);

                    $clave = $primero->regimen_impositivo->value.'|'.$linea->tipo_impositivo;
                    $lineasPorImpuesto[$clave]['tipo_impositivo'] = $linea->tipo_impositivo;
                    $lineasPorImpuesto[$clave]['base'] = ($lineasPorImpuesto[$clave]['base'] ?? 0) + (float) $linea->base;
                    $lineasPorImpuesto[$clave]['cuota'] = ($lineasPorImpuesto[$clave]['cuota'] ?? 0) + (float) $linea->cuota_impuesto;
                }
            }

            foreach ($lineasPorImpuesto as $grupo) {
                $factura->impuestos()->create([
                    'tenant_id' => $primero->tenant_id,
                    'tipo_impuesto' => $primero->regimen_impositivo->value,
                    'porcentaje' => $grupo['tipo_impositivo'],
                    'base_imponible' => round($grupo['base'], 2),
                    'cuota' => round($grupo['cuota'], 2),
                ]);
            }

            Albaran::withoutGlobalScopes()->whereIn('id', $ids)->update([
                'estado' => EstadoAlbaran::Facturado,
                'convertido_a_factura_id' => $factura->id,
            ]);

            return $factura;
        });
    }
}
