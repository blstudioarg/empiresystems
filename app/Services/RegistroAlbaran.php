<?php

namespace App\Services;

use App\Enums\EstadoAlbaran;
use App\Exceptions\CantidadAlbaranExcedeLoPendienteException;
use App\Models\Albaran;
use App\Models\Cliente;
use App\Models\Presupuesto;
use App\Models\PresupuestoLinea;
use Illuminate\Support\Facades\DB;

/**
 * Único punto de escritura de albaranes (research D1): reutiliza `CalculadoraFactura` para los
 * importes, igual que `RegistroPresupuesto`. Si el albarán nace de un presupuesto, acota cada
 * línea a la cantidad pendiente de entrega (research D2, FR-003); si es directo a cliente, admite
 * líneas libres con el mismo criterio de un presupuesto nuevo.
 */
class RegistroAlbaran
{
    public function __construct(private readonly CalculadoraFactura $calculadora) {}

    /**
     * @param  array<string, mixed>  $datos
     */
    public function guardar(array $datos, ?Albaran $albaran = null): Albaran
    {
        $tenantId = tenant()->id;
        $presupuesto = ! empty($datos['presupuesto_id']) ? Presupuesto::findOrFail($datos['presupuesto_id']) : null;
        $cliente = Cliente::findOrFail($datos['cliente_id'] ?? $presupuesto?->cliente_id);

        $regimen = $presupuesto?->regimen_impositivo ?? tenant()->regimen_impositivo;
        $aplicaRecargo = $presupuesto?->aplica_recargo ?? ($cliente->aplica_recargo_equivalencia ?? false);

        $lineasPorPresupuesto = [];
        if ($presupuesto) {
            $lineasPorPresupuesto = $this->validarLineasDePresupuesto($presupuesto, $datos['lineas'], $albaran);
        }

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

        return DB::transaction(function () use ($datos, $cliente, $presupuesto, $regimen, $aplicaRecargo, $resultado, $albaran, $tenantId, $lineasPorPresupuesto) {
            $cabecera = [
                'presupuesto_id' => $presupuesto?->id,
                'cliente_id' => $cliente->id,
                'estado' => EstadoAlbaran::Borrador,
                'receptor_nombre' => $cliente->razon_social ?: $cliente->nombre,
                'receptor_nif' => $cliente->nif,
                'receptor_direccion' => $cliente->direccion,
                'receptor_cp' => $cliente->cp,
                'receptor_ciudad' => $cliente->ciudad,
                'receptor_provincia' => $cliente->provincia,
                'receptor_pais' => $cliente->pais ?? 'ES',
                'regimen_impositivo' => $regimen,
                'aplica_recargo' => $aplicaRecargo,
                'base_total' => $resultado->baseTotal,
                'cuota_impuesto_total' => $resultado->cuotaImpuestoTotal,
                'cuota_recargo_total' => $resultado->cuotaRecargoTotal,
                'total' => $resultado->total,
                'notas' => $datos['notas'] ?? null,
            ];

            if ($albaran) {
                $albaran->update($cabecera);
                $albaran->lineas()->delete();
            } else {
                $cabecera['tenant_id'] = $tenantId;
                $cabecera['numero'] = $this->siguienteNumero($tenantId);
                $albaran = Albaran::create($cabecera);
            }

            foreach ($datos['lineas'] as $orden => $lineaDatos) {
                $calculo = $resultado->lineas[$orden];

                $albaran->lineas()->create([
                    'presupuesto_linea_id' => $lineasPorPresupuesto[$orden] ?? null,
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

            return $albaran;
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $lineas
     * @return array<int, int> orden => presupuesto_linea_id
     */
    private function validarLineasDePresupuesto(Presupuesto $presupuesto, array $lineas, ?Albaran $albaranEnEdicion): array
    {
        // Al reeditar un albarán en borrador, sus líneas actuales no cuentan como "ya entregadas"
        // para el propio recálculo del tope (se van a reemplazar en la misma transacción).
        $cantidadYaEnEsteAlbaran = [];
        if ($albaranEnEdicion) {
            foreach ($albaranEnEdicion->lineas as $lineaExistente) {
                if ($lineaExistente->presupuesto_linea_id) {
                    $cantidadYaEnEsteAlbaran[$lineaExistente->presupuesto_linea_id] =
                        ($cantidadYaEnEsteAlbaran[$lineaExistente->presupuesto_linea_id] ?? 0) + (float) $lineaExistente->cantidad;
                }
            }
        }

        $resultado = [];

        foreach ($lineas as $orden => $lineaDatos) {
            $presupuestoLineaId = $lineaDatos['presupuesto_linea_id'] ?? null;

            if (! $presupuestoLineaId) {
                continue;
            }

            /** @var PresupuestoLinea $presupuestoLinea */
            $presupuestoLinea = PresupuestoLinea::where('presupuesto_id', $presupuesto->id)
                ->findOrFail($presupuestoLineaId);

            $pendiente = $presupuestoLinea->cantidadPendiente() + ($cantidadYaEnEsteAlbaran[$presupuestoLineaId] ?? 0);

            if ((float) $lineaDatos['cantidad'] > $pendiente) {
                throw new CantidadAlbaranExcedeLoPendienteException(
                    "La cantidad solicitada supera lo pendiente de entrega ({$pendiente}) para «{$presupuestoLinea->concepto}»."
                );
            }

            $resultado[$orden] = (int) $presupuestoLineaId;
        }

        return $resultado;
    }

    private function siguienteNumero(int $tenantId): string
    {
        $anio = (int) now()->year;

        $contador = Albaran::withoutGlobalScopes()
            ->withTrashed()
            ->where('tenant_id', $tenantId)
            ->whereYear('created_at', $anio)
            ->lockForUpdate()
            ->count();

        return sprintf('A-%d-%04d', $anio, $contador + 1);
    }
}
