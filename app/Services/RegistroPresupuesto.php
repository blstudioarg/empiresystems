<?php

namespace App\Services;

use App\Enums\EstadoPresupuesto;
use App\Models\Cliente;
use App\Models\Lead;
use App\Models\Presupuesto;
use App\Support\ConfigCrm;
use Illuminate\Support\Facades\DB;

/**
 * Único punto de escritura de presupuestos (research D1): reutiliza `CalculadoraFactura` para
 * garantizar totales idénticos al céntimo a una factura equivalente (SC-003). No duplica el motor
 * de cálculo.
 */
class RegistroPresupuesto
{
    public function __construct(private readonly CalculadoraFactura $calculadora) {}

    /**
     * @param  array<string, mixed>  $datos
     */
    public function guardar(array $datos, ?Presupuesto $presupuesto = null): Presupuesto
    {
        $tenantId = tenant()->id;
        $cliente = ! empty($datos['cliente_id']) ? Cliente::findOrFail($datos['cliente_id']) : null;
        $lead = ! empty($datos['lead_id']) ? Lead::findOrFail($datos['lead_id']) : null;

        $regimen = tenant()->regimen_impositivo;
        $aplicaRecargo = $cliente?->aplica_recargo_equivalencia ?? false;

        $resultado = $this->calculadora->calcular(
            regimen: $regimen,
            aplicaRecargo: $aplicaRecargo,
            irpfPorcentaje: isset($datos['irpf_porcentaje']) ? (float) $datos['irpf_porcentaje'] : null,
            lineas: collect($datos['lineas'])->map(fn (array $linea) => [
                'cantidad' => (float) $linea['cantidad'],
                'precioUnitario' => (float) $linea['precio_unitario'],
                'descuentoPorcentaje' => isset($linea['descuento_porcentaje']) ? (float) $linea['descuento_porcentaje'] : null,
                'tipoImpositivo' => (float) $linea['tipo_impositivo'],
            ])->all(),
        );

        return DB::transaction(function () use ($datos, $cliente, $lead, $regimen, $aplicaRecargo, $resultado, $presupuesto, $tenantId) {
            $receptor = $cliente ?? $lead;

            $cabecera = [
                'oportunidad_id' => $datos['oportunidad_id'] ?? null,
                'cliente_id' => $cliente?->id,
                'lead_id' => $lead?->id,
                'estado' => EstadoPresupuesto::Borrador,
                'receptor_nombre' => $cliente ? ($cliente->razon_social ?: $cliente->nombre) : $lead?->nombre,
                'receptor_nif' => $cliente?->nif,
                'receptor_direccion' => $cliente?->direccion,
                'receptor_cp' => $cliente?->cp,
                'receptor_ciudad' => $cliente?->ciudad,
                'receptor_provincia' => $cliente?->provincia,
                'receptor_pais' => $cliente?->pais ?? 'ES',
                'fecha_emision' => $datos['fecha_emision'],
                'fecha_validez' => $datos['fecha_validez'] ?? now()->parse($datos['fecha_emision'])->addDays(ConfigCrm::diasValidezPresupuesto($tenantId))->toDateString(),
                'regimen_impositivo' => $regimen,
                'aplica_recargo' => $aplicaRecargo,
                'base_total' => $resultado->baseTotal,
                'cuota_impuesto_total' => $resultado->cuotaImpuestoTotal,
                'cuota_recargo_total' => $resultado->cuotaRecargoTotal,
                'irpf_porcentaje' => $datos['irpf_porcentaje'] ?? null,
                'irpf_cuota' => $resultado->irpfCuota,
                'total' => $resultado->total,
                'notas' => $datos['notas'] ?? null,
            ];

            if ($presupuesto) {
                $presupuesto->update($cabecera);
                $presupuesto->lineas()->delete();
            } else {
                $cabecera['tenant_id'] = $tenantId;
                $cabecera['numero'] = $this->siguienteNumero($tenantId, (int) now()->parse($datos['fecha_emision'])->format('Y'));
                $presupuesto = Presupuesto::create($cabecera);
            }

            foreach ($datos['lineas'] as $orden => $lineaDatos) {
                $calculo = $resultado->lineas[$orden];

                $presupuesto->lineas()->create([
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

            return $presupuesto;
        });
    }

    private function siguienteNumero(int $tenantId, int $anio): string
    {
        $contador = Presupuesto::withoutGlobalScopes()
            ->withTrashed()
            ->where('tenant_id', $tenantId)
            ->whereYear('fecha_emision', $anio)
            ->lockForUpdate()
            ->count();

        return sprintf('P-%d-%04d', $anio, $contador + 1);
    }
}
