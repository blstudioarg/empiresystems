<?php

namespace App\Services;

use App\Enums\EstadoFactura;
use App\Enums\TipoFactura;
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

        return DB::transaction(function () use ($datos, $receptor, $cliente, $regimen, $aplicaRecargo, $resultado) {
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
                'forma_pago' => \App\Enums\FormaPago::Efectivo,
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

            // Emisión: número correlativo de la serie "S" con bloqueo, evento e inmutabilidad.
            return $this->emisor->emitir($factura);
        });
    }
}
