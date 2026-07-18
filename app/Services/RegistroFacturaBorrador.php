<?php

namespace App\Services;

use App\Enums\EstadoFactura;
use App\Enums\FormaPago;
use App\Enums\TipoFactura;
use App\Models\Cliente;
use App\Models\Factura;
use App\Models\Serie;
use App\Support\VencimientoFactura;
use Illuminate\Support\Facades\DB;

/**
 * Punto de escritura de facturas EN BORRADOR para el asistente IA (feature 030, US3).
 *
 * Reutiliza `CalculadoraFactura` (Principio III: los importes los calcula el servidor, nunca el
 * modelo). Solo crea/edita facturas ordinarias en estado `borrador` — jamás emite ni toca facturas
 * ya emitidas (inmutabilidad, Principio II). No es el camino de emisión: es equivalente a "guardar
 * borrador" desde la UI, acotado a lo que el asistente puede proponer.
 */
class RegistroFacturaBorrador
{
    public function __construct(private readonly CalculadoraFactura $calculadora) {}

    /**
     * @param  array<int, array{articulo_id: int|null, concepto: string, unidad: ?string, cantidad: float, precio_unitario: float, tipo_impositivo: float}>  $lineas
     */
    public function crear(Cliente $cliente, array $lineas, ?Factura $factura = null): Factura
    {
        if ($factura !== null && $factura->estado !== EstadoFactura::Borrador) {
            throw new \RuntimeException('Solo se pueden editar facturas en borrador.');
        }

        $regimen = tenant()->regimen_impositivo;
        $aplicaRecargo = $cliente->aplica_recargo_equivalencia;
        $serie = Serie::activaPorTipo(TipoFactura::Ordinaria);
        $fechaExpedicion = now()->toDateString();

        $resultado = $this->calculadora->calcular(
            regimen: $regimen,
            aplicaRecargo: $aplicaRecargo,
            irpfPorcentaje: null,
            lineas: array_map(fn (array $l) => [
                'cantidad' => (float) $l['cantidad'],
                'precioUnitario' => (float) $l['precio_unitario'],
                'descuentoPorcentaje' => null,
                'tipoImpositivo' => (float) $l['tipo_impositivo'],
            ], $lineas),
        );

        return DB::transaction(function () use ($cliente, $lineas, $factura, $serie, $regimen, $aplicaRecargo, $resultado, $fechaExpedicion) {
            $cabecera = [
                'serie_id' => $serie->id,
                'tipo' => TipoFactura::Ordinaria,
                'estado' => EstadoFactura::Borrador,
                'cliente_id' => $cliente->id,
                'cliente_nombre' => $cliente->nombre,
                'cliente_razon_social' => $cliente->razon_social,
                'cliente_nif' => $cliente->nif,
                'cliente_direccion' => $cliente->direccion,
                'cliente_cp' => $cliente->cp,
                'cliente_ciudad' => $cliente->ciudad,
                'cliente_provincia' => $cliente->provincia,
                'cliente_pais' => $cliente->pais ?? 'ES',
                'fecha_expedicion' => $fechaExpedicion,
                'fecha_vencimiento' => VencimientoFactura::calcular($fechaExpedicion),
                'forma_pago' => FormaPago::Transferencia->value,
                'moneda' => 'EUR',
                'regimen_impositivo' => $regimen,
                'aplica_recargo' => $aplicaRecargo,
                'base_total' => $resultado->baseTotal,
                'cuota_impuesto_total' => $resultado->cuotaImpuestoTotal,
                'cuota_recargo_total' => $resultado->cuotaRecargoTotal,
                'irpf_porcentaje' => null,
                'irpf_cuota' => $resultado->irpfCuota,
                'total' => $resultado->total,
            ];

            if ($factura) {
                $factura->update($cabecera);
                $factura->lineas()->delete();
                $factura->impuestos()->delete();
            } else {
                $factura = Factura::create($cabecera);
            }

            foreach ($lineas as $orden => $linea) {
                $calculo = $resultado->lineas[$orden];

                $factura->lineas()->create([
                    'articulo_id' => $linea['articulo_id'] ?? null,
                    'concepto' => $linea['concepto'],
                    'unidad' => $linea['unidad'] ?? null,
                    'cantidad' => $linea['cantidad'],
                    'precio_unitario' => $linea['precio_unitario'],
                    'descuento_porcentaje' => null,
                    'base' => $calculo['base'],
                    'tipo_impositivo' => $linea['tipo_impositivo'],
                    'cuota_impuesto' => $calculo['cuotaImpuesto'],
                    'tipo_recargo' => $calculo['tipoRecargo'],
                    'cuota_recargo' => $calculo['cuotaRecargo'],
                    'orden' => $orden,
                ]);
            }

            foreach ($resultado->impuestos as $impuesto) {
                $factura->impuestos()->create([
                    'tipo_impuesto' => $impuesto['tipoImpuesto'],
                    'porcentaje' => $impuesto['porcentaje'],
                    'base_imponible' => $impuesto['baseImponible'],
                    'cuota' => $impuesto['cuota'],
                ]);
            }

            return $factura;
        });
    }
}
