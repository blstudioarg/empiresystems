<?php

namespace App\Services;

use App\Enums\EstadoFactura;
use App\Enums\FormaPago;
use App\Enums\TipoFactura;
use App\Exceptions\PagoTicketDescuadradoException;
use App\Exceptions\TicketFueraDeTopeException;
use App\Models\Cliente;
use App\Models\Factura;
use App\Models\Serie;
use App\Support\TopeSimplificada;
use App\Support\VencimientoFactura;
use Illuminate\Support\Facades\DB;

/**
 * Crea y emite un ticket simplificado (tipo = simplificada) en una única operación atómica.
 *
 * Reutiliza el motor fiscal existente: cálculo server-side (CalculadoraFactura), numeración con
 * bloqueo por serie/año e inmutabilidad/eventos (EmisorFacturas). Añade la regla propia de la
 * simplificada: bloqueo duro de tope de importe (TopeSimplificada) y receptor opcional.
 */
class RegistroTicket
{
    public function __construct(
        private readonly CalculadoraFactura $calculadora,
        private readonly EmisorFacturas $emisor,
        private readonly TopeSimplificada $tope,
    ) {}

    /**
     * @param  array<string, mixed>  $datos  Datos validados: lineas[], receptor?, notas?
     */
    public function registrar(array $datos): Factura
    {
        $regimen = tenant()->regimen_impositivo;

        $receptor = $datos['receptor'] ?? [];
        $cliente = ! empty($receptor['cliente_id'])
            ? Cliente::find($receptor['cliente_id'])
            : null;

        $aplicaRecargo = $cliente?->aplica_recargo_equivalencia ?? false;

        $resultado = $this->calculadora->calcular(
            regimen: $regimen,
            aplicaRecargo: $aplicaRecargo,
            irpfPorcentaje: null,
            lineas: collect($datos['lineas'])->map(fn (array $linea) => [
                'cantidad' => (float) $linea['cantidad'],
                'precioUnitario' => (float) $linea['precio_unitario'],
                'descuentoPorcentaje' => isset($linea['descuento_porcentaje']) ? (float) $linea['descuento_porcentaje'] : null,
                'tipoImpositivo' => (float) $linea['tipo_impositivo'],
            ])->all(),
        );

        // Importe bruto (impuestos incluidos) para el tope legal de la simplificada.
        $importeBruto = round(
            $resultado->baseTotal + $resultado->cuotaImpuestoTotal + $resultado->cuotaRecargoTotal,
            2,
        );

        $topeAplicable = $this->tope->topePara();

        // Comparación en céntimos para evitar falsos positivos por coma flotante en el límite.
        if ((int) round($importeBruto * 100) > (int) round($topeAplicable * 100)) {
            throw TicketFueraDeTopeException::paraTope($topeAplicable);
        }

        // Desglose de cobro (pago simple o dividido); debe cuadrar al céntimo con el total.
        $pagos = $this->resolverPagos($datos['pagos'] ?? null, (float) $resultado->total);
        $formaPago = $this->formaPagoPredominante($pagos);

        return DB::transaction(function () use ($datos, $receptor, $cliente, $regimen, $aplicaRecargo, $resultado, $pagos, $formaPago) {
            $serie = Serie::activaPorTipo(TipoFactura::Simplificada);
            $hoy = now()->toDateString();

            $factura = Factura::create([
                'serie_id' => $serie->id,
                'tipo' => TipoFactura::Simplificada,
                'estado' => EstadoFactura::Borrador,
                'cliente_id' => $cliente?->id,
                'cliente_nombre' => $receptor['cliente_nombre'] ?? null,
                'cliente_razon_social' => $receptor['cliente_razon_social'] ?? null,
                'cliente_nif' => $receptor['cliente_nif'] ?? null,
                'cliente_direccion' => $receptor['cliente_direccion'] ?? null,
                'cliente_cp' => $receptor['cliente_cp'] ?? null,
                'cliente_ciudad' => $receptor['cliente_ciudad'] ?? null,
                'cliente_provincia' => $receptor['cliente_provincia'] ?? null,
                'cliente_pais' => $receptor['cliente_pais'] ?? 'ES',
                'fecha_expedicion' => $hoy,
                'fecha_operacion' => null,
                'fecha_vencimiento' => VencimientoFactura::calcular($hoy),
                'forma_pago' => $formaPago,
                'moneda' => 'EUR',
                'regimen_impositivo' => $regimen,
                'aplica_recargo' => $aplicaRecargo,
                'base_total' => $resultado->baseTotal,
                'cuota_impuesto_total' => $resultado->cuotaImpuestoTotal,
                'cuota_recargo_total' => $resultado->cuotaRecargoTotal,
                'irpf_porcentaje' => null,
                'irpf_cuota' => $resultado->irpfCuota,
                'total' => $resultado->total,
                'notas' => $datos['notas'] ?? null,
            ]);

            foreach ($datos['lineas'] as $orden => $lineaDatos) {
                $calculo = $resultado->lineas[$orden];

                $factura->lineas()->create([
                    'articulo_id' => $lineaDatos['articulo_id'] ?? null,
                    'concepto' => $lineaDatos['concepto'],
                    'unidad' => $lineaDatos['unidad'] ?? null,
                    'cantidad' => $lineaDatos['cantidad'],
                    'precio_unitario' => $lineaDatos['precio_unitario'],
                    'descuento_porcentaje' => $lineaDatos['descuento_porcentaje'] ?? null,
                    'base' => $calculo['base'],
                    'tipo_impositivo' => $lineaDatos['tipo_impositivo'],
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

            // Desglose interno de cómo se cobró en caja (uno o varios métodos).
            foreach ($pagos as $pago) {
                $factura->pagosTicket()->create([
                    'metodo' => $pago['metodo'],
                    'importe' => $pago['importe'],
                ]);
            }

            // Emisión: número correlativo de la serie "S" con bloqueo, evento e inmutabilidad.
            return $this->emisor->emitir($factura);
        });
    }

    /**
     * Normaliza y valida el desglose de cobro contra el total del ticket. Si no se aporta desglose,
     * asume un único pago íntegro en efectivo (comportamiento por defecto del TPV). Si se aporta,
     * la suma de importes debe cuadrar al céntimo con el total; si no, lanza excepción.
     *
     * @param  list<array{metodo: string, importe: mixed}>|null  $pagosDatos
     * @return list<array{metodo: FormaPago, importe: float}>
     */
    private function resolverPagos(?array $pagosDatos, float $total): array
    {
        if (empty($pagosDatos)) {
            return [['metodo' => FormaPago::Efectivo, 'importe' => round($total, 2)]];
        }

        $pagos = array_map(fn (array $pago) => [
            'metodo' => $pago['metodo'] instanceof FormaPago ? $pago['metodo'] : FormaPago::from($pago['metodo']),
            'importe' => round((float) $pago['importe'], 2),
        ], array_values($pagosDatos));

        $asignado = array_sum(array_column($pagos, 'importe'));

        if ((int) round($asignado * 100) !== (int) round($total * 100)) {
            throw PagoTicketDescuadradoException::paraTotal($total, $asignado);
        }

        return $pagos;
    }

    /**
     * Método de pago "principal" del ticket para el único campo `forma_pago` de la factura (que se
     * muestra en el PDF): el de mayor importe del reparto. El desglose completo vive en `ticket_pagos`.
     *
     * @param  list<array{metodo: FormaPago, importe: float}>  $pagos
     */
    private function formaPagoPredominante(array $pagos): FormaPago
    {
        $principal = collect($pagos)->sortByDesc('importe')->first();

        return $principal['metodo'] ?? FormaPago::Efectivo;
    }
}
