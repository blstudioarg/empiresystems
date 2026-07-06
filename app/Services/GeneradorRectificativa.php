<?php

namespace App\Services;

use App\Enums\EstadoFactura;
use App\Enums\TipoFactura;
use App\Enums\TipoRectificacion;
use App\Exceptions\FacturaNoRectificableException;
use App\Models\Factura;
use App\Models\Serie;
use Illuminate\Support\Facades\DB;

class GeneradorRectificativa
{
    public function generar(Factura $original, TipoRectificacion $modalidad, string $motivo): Factura
    {
        $this->validar($original, $modalidad);

        return DB::transaction(function () use ($original, $modalidad, $motivo) {
            $serieRectificativa = Serie::activaPorTipo(TipoFactura::Rectificativa);

            $rectificativa = Factura::create([
                'serie_id' => $serieRectificativa->id,
                'tipo' => TipoFactura::Rectificativa,
                'estado' => EstadoFactura::Borrador,
                'es_rectificativa' => true,
                'factura_rectificada_id' => $original->id,
                'motivo_rectificacion' => $motivo,
                'tipo_rectificacion' => $modalidad,
                'cliente_id' => $original->cliente_id,
                'cliente_nombre' => $original->cliente_nombre,
                'cliente_razon_social' => $original->cliente_razon_social,
                'cliente_nif' => $original->cliente_nif,
                'cliente_direccion' => $original->cliente_direccion,
                'cliente_cp' => $original->cliente_cp,
                'cliente_ciudad' => $original->cliente_ciudad,
                'cliente_provincia' => $original->cliente_provincia,
                'cliente_pais' => $original->cliente_pais,
                'fecha_expedicion' => now()->toDateString(),
                'fecha_operacion' => $original->fecha_operacion,
                'fecha_vencimiento' => null,
                'forma_pago' => $original->forma_pago,
                'moneda' => $original->moneda,
                'regimen_impositivo' => $original->regimen_impositivo,
                'aplica_recargo' => $original->aplica_recargo,
                'base_total' => $original->base_total,
                'cuota_impuesto_total' => $original->cuota_impuesto_total,
                'cuota_recargo_total' => $original->cuota_recargo_total,
                'irpf_porcentaje' => $original->irpf_porcentaje,
                'irpf_cuota' => $original->irpf_cuota,
                'total' => $original->total,
                'notas' => $original->notas,
            ]);

            foreach ($original->lineas as $linea) {
                $rectificativa->lineas()->create([
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
                    'orden' => $linea->orden,
                ]);
            }

            foreach ($original->impuestos as $impuesto) {
                $rectificativa->impuestos()->create([
                    'tipo_impuesto' => $impuesto->tipo_impuesto,
                    'porcentaje' => $impuesto->porcentaje,
                    'base_imponible' => $impuesto->base_imponible,
                    'cuota' => $impuesto->cuota,
                ]);
            }

            return $rectificativa;
        });
    }

    private function validar(Factura $original, TipoRectificacion $modalidad): void
    {
        if ($original->estado === EstadoFactura::Rectificada) {
            throw new FacturaNoRectificableException('Esta factura ya fue rectificada.');
        }

        if ($original->estado !== EstadoFactura::Emitida) {
            throw new FacturaNoRectificableException('Solo se pueden rectificar facturas emitidas.');
        }

        // Por sustitución la deuda pasa a la rectificativa y la original se cierra al cobro; los
        // cobros ya registrados quedarían atrapados en la original. Se exige anularlos primero para
        // no descuadrar el saldo. Por diferencias no aplica: la original sigue siendo el documento
        // de cobro y conserva sus pagos.
        if ($modalidad === TipoRectificacion::Sustitucion && $original->montoCobrado() > 0) {
            throw new FacturaNoRectificableException('La factura tiene cobros registrados. Anúlalos antes de rectificar por sustitución.');
        }
    }
}
