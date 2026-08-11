<?php

namespace App\Services;

use App\Models\PosCuenta;
use App\Models\PosMesa;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Mover una cuenta a otra mesa y juntar dos cuentas (feature 038, US6).
 *
 * Regla que gobierna las dos operaciones: **nunca se pierde consumo**. Transferir a una mesa
 * ocupada no puede "pisar" la cuenta que ya estaba ahí; lo que corresponde entonces es unir
 * (FR-036), y el controlador ofrece esa salida en vez de fallar en seco.
 *
 * Con cobros parciales de por medio se mueve **solo lo pendiente** (FR-032): lo ya emitido es un
 * documento inmutable que pertenece a la cuenta donde se cobró y no puede viajar a otra mesa.
 */
class TransferidorCuenta
{
    public function transferir(PosCuenta $cuenta, ?PosMesa $destino): PosCuenta
    {
        $this->exigirAbierta($cuenta);

        if ($destino !== null && $this->cuentaAbiertaDe($destino, $cuenta->id) !== null) {
            throw ValidationException::withMessages([
                'mesa_id' => 'Esa mesa ya tiene una cuenta abierta. Únelas si quieres juntar el consumo.',
            ]);
        }

        return DB::transaction(function () use ($cuenta, $destino) {
            $cuenta->mesa_id = $destino?->id;
            $cuenta->version = (int) $cuenta->version + 1;
            $cuenta->save();

            return $cuenta->refresh();
        });
    }

    /**
     * Une `$origen` dentro de `$destino`: las líneas **pendientes** del origen pasan al destino y
     * el origen queda cerrado. Se conservan todas las líneas y los importes se suman (FR-035).
     */
    public function unir(PosCuenta $origen, PosCuenta $destino): PosCuenta
    {
        $this->exigirAbierta($origen);
        $this->exigirAbierta($destino);

        if ($origen->id === $destino->id) {
            throw ValidationException::withMessages([
                'cuenta_destino_id' => 'No se puede unir una cuenta consigo misma.',
            ]);
        }

        return DB::transaction(function () use ($origen, $destino) {
            $origen->load('lineas.opciones');
            $orden = (int) $destino->lineas()->max('orden');

            foreach ($origen->lineas as $linea) {
                $pendiente = $linea->cantidadPendiente();

                if ($pendiente <= 0) {
                    // Ya cobrada por completo: se queda con su cuenta de origen y su documento.
                    continue;
                }

                $nueva = $destino->lineas()->create([
                    'tenant_id' => $destino->tenant_id,
                    'articulo_id' => $linea->articulo_id,
                    'concepto' => $linea->concepto,
                    'unidad' => $linea->unidad,
                    'cantidad' => $pendiente,
                    'precio_unitario' => $linea->precio_unitario,
                    'suplemento_opciones' => $linea->suplemento_opciones,
                    'tipo_impositivo' => $linea->tipo_impositivo,
                    'cantidad_saldada' => 0,
                    'orden' => ++$orden,
                ]);

                foreach ($linea->opciones as $opcion) {
                    $nueva->opciones()->create([
                        'tenant_id' => $destino->tenant_id,
                        'opcion_id' => $opcion->opcion_id,
                        'nombre' => $opcion->nombre,
                        'precio' => $opcion->precio,
                        'articulo_vinculado_id' => $opcion->articulo_vinculado_id,
                    ]);
                }

                // Lo movido deja de estar pendiente en el origen, sin borrar la línea: el rastro
                // de qué se comandó en esa mesa se conserva.
                $linea->cantidad_saldada = $linea->cantidad;
                $linea->save();
            }

            $origen->estado = PosCuenta::ESTADO_CERRADA;
            $origen->cerrada_en = now();
            $origen->version = (int) $origen->version + 1;
            $origen->save();

            $destino->version = (int) $destino->version + 1;
            $destino->save();

            return $destino->refresh();
        });
    }

    private function exigirAbierta(PosCuenta $cuenta): void
    {
        if (! $cuenta->estaAbierta()) {
            throw ValidationException::withMessages([
                'cuenta' => 'Solo se pueden mover o unir cuentas abiertas.',
            ]);
        }
    }

    private function cuentaAbiertaDe(PosMesa $mesa, ?int $exceptoCuentaId = null): ?PosCuenta
    {
        return PosCuenta::query()
            ->where('mesa_id', $mesa->id)
            ->where('estado', PosCuenta::ESTADO_ABIERTA)
            ->when($exceptoCuentaId !== null, fn ($q) => $q->where('id', '!=', $exceptoCuentaId))
            ->first();
    }
}
