<?php

namespace App\Services;

use App\Enums\OrigenMovimientoStock;
use App\Enums\TipoArticulo;
use App\Enums\TipoMovimientoStock;
use App\Models\Factura;
use App\Models\PosCobro;
use App\Models\PosCuenta;
use App\Models\PosCuentaLinea;
use App\Support\ConfigPos;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Cobra una cuenta abierta, entera o por partes (feature 038).
 *
 * **No hay un segundo camino de emisión.** Este servicio solo traduce "estas unidades de estas
 * líneas" al array `lineas[]` que {@see RegistroTicket} ya espera, y lo invoca sin modificar su
 * contrato (research.md D3). Consecuencia deliberada: numeración correlativa, tope legal,
 * inmutabilidad y encadenamiento Verifactu se heredan intactos en vez de duplicarse — que es
 * justo lo que prohíbe el Principio III.
 *
 * El **suplemento de zona** se materializa aquí subiendo el `precio_unitario` de cada línea antes
 * de pasarla al motor (research.md D4). Como el `tipo_impositivo` viaja en la línea y
 * `CalculadoraFactura` ya agrupa el desglose por tipo, el suplemento hereda automáticamente el
 * tipo del artículo que lo genera: cero lógica fiscal nueva y ninguna regla de reparto entre
 * tipos impositivos.
 */
class CobradorCuenta
{
    public function __construct(
        private readonly RegistroTicket $registroTicket,
        private readonly RegistroMovimientoStock $registroMovimientoStock,
    ) {}

    /**
     * @param  array<int, float>  $seleccion  cuenta_linea_id => unidades a cobrar. Vacío = todo lo pendiente.
     * @param  list<array{metodo: string, importe: mixed}>|null  $pagos
     * @param  array<string, mixed>|null  $receptor
     * @return array{factura: Factura, cobro: PosCobro, cuenta_cerrada: bool, pendiente: float}
     */
    public function cobrar(
        PosCuenta $cuenta,
        array $seleccion = [],
        ?array $pagos = null,
        ?array $receptor = null,
        ?int $usuarioId = null,
    ): array {
        $tenantId = (int) $cuenta->tenant_id;

        if (! $cuenta->estaAbierta()) {
            throw ValidationException::withMessages([
                'cuenta' => 'Esta cuenta ya no está abierta: no se puede cobrar.',
            ]);
        }

        $cuenta->load('lineas.opciones', 'mesa.zona');

        $esParcial = $seleccion !== [];

        if ($esParcial && ! ConfigPos::cobroDivididoActivo($tenantId)) {
            throw ValidationException::withMessages([
                'lineas' => 'El cobro por selección de líneas no está activado para esta empresa.',
            ]);
        }

        $unidades = $this->resolverUnidades($cuenta, $seleccion, $esParcial);

        // El suplemento se toma de la zona DONDE SE COBRA y con el valor VIGENTE AHORA (FR-051):
        // si la cuenta se transfirió desde otra zona, manda la de destino.
        $suplemento = ConfigPos::suplementoZonaActivo($tenantId)
            ? (float) ($cuenta->mesa?->zona?->suplemento_porcentaje ?? 0)
            : 0.0;

        $lineasPayload = [];
        foreach ($cuenta->lineas as $linea) {
            $cantidad = $unidades[$linea->id] ?? 0.0;

            if ($cantidad <= 0) {
                continue;
            }

            $lineasPayload[] = [
                'articulo_id' => $linea->articulo_id,
                'concepto' => $this->conceptoConOpciones($linea),
                'unidad' => $linea->unidad,
                'cantidad' => $cantidad,
                // Redondeo POR LÍNEA (no al final) para que el total impreso sea la suma exacta
                // de los importes de línea impresos — misma regla que CalculadoraFactura.
                'precio_unitario' => round($linea->precioEfectivo() * (1 + $suplemento / 100), 2),
                'tipo_impositivo' => (float) $linea->tipo_impositivo,
            ];
        }

        $datos = ['lineas' => $lineasPayload];

        if ($pagos !== null && $pagos !== []) {
            $datos['pagos'] = $pagos;
        }

        $receptorEfectivo = $receptor ?? $this->receptorDeLaCuenta($cuenta);
        if ($receptorEfectivo !== null) {
            $datos['receptor'] = $receptorEfectivo;
        }

        return DB::transaction(function () use ($cuenta, $datos, $unidades, $suplemento, $usuarioId, $tenantId) {
            // El tope de la simplificada, la numeración y Verifactu los aplica RegistroTicket
            // sobre ESTE importe: un cobro parcial es, para él, un ticket normal con menos líneas.
            $factura = $this->registroTicket->registrar($datos);

            $cobro = PosCobro::create([
                'tenant_id' => $tenantId,
                'cuenta_id' => $cuenta->id,
                'factura_id' => $factura->id,
                'zona_suplemento_aplicado' => $suplemento,
                'cobrado_por' => $usuarioId,
            ]);

            foreach ($cuenta->lineas as $linea) {
                $cantidad = $unidades[$linea->id] ?? 0.0;

                if ($cantidad <= 0) {
                    continue;
                }

                $cobro->lineas()->create([
                    'tenant_id' => $tenantId,
                    'cuenta_linea_id' => $linea->id,
                    'cantidad' => $cantidad,
                ]);

                $linea->cantidad_saldada = round((float) $linea->cantidad_saldada + $cantidad, 2);
                $linea->save();

                $this->moverStockDeOpcionesVinculadas($linea, $cantidad, $factura);
            }

            $cuenta->version = (int) $cuenta->version + 1;

            $cuenta->load('lineas');
            $cerrada = $cuenta->estaSaldada();

            if ($cerrada) {
                $cuenta->estado = PosCuenta::ESTADO_CERRADA;
                $cuenta->cerrada_en = now();
            }

            $cuenta->save();

            return [
                'factura' => $factura,
                'cobro' => $cobro,
                'cuenta_cerrada' => $cerrada,
                'pendiente' => $cuenta->pendiente(),
            ];
        });
    }

    /**
     * Traduce la selección a un mapa `linea_id => unidades`, validando que cada línea pertenece a
     * la cuenta y que no se piden más unidades de las pendientes (FR-029: es lo que hace
     * imposible cobrar dos veces lo mismo).
     *
     * @param  array<int, float>  $seleccion
     * @return array<int, float>
     */
    private function resolverUnidades(PosCuenta $cuenta, array $seleccion, bool $esParcial): array
    {
        $porId = $cuenta->lineas->keyBy('id');
        $unidades = [];

        if (! $esParcial) {
            foreach ($cuenta->lineas as $linea) {
                $pendiente = $linea->cantidadPendiente();
                if ($pendiente > 0) {
                    $unidades[$linea->id] = $pendiente;
                }
            }

            if ($unidades === []) {
                throw ValidationException::withMessages([
                    'lineas' => 'Esta cuenta no tiene nada pendiente de cobro.',
                ]);
            }

            return $unidades;
        }

        foreach ($seleccion as $lineaId => $cantidad) {
            $linea = $porId->get((int) $lineaId);

            if ($linea === null) {
                throw ValidationException::withMessages([
                    'lineas' => 'Alguna de las líneas seleccionadas no pertenece a esta cuenta.',
                ]);
            }

            $cantidad = round((float) $cantidad, 2);

            if ($cantidad <= 0) {
                continue;
            }

            if ($cantidad > $linea->cantidadPendiente()) {
                throw ValidationException::withMessages([
                    'lineas' => "No quedan tantas unidades pendientes de «{$linea->concepto}».",
                ]);
            }

            $unidades[$linea->id] = $cantidad;
        }

        if ($unidades === []) {
            // No se emite un documento sin líneas: sería una factura a 0 €.
            throw ValidationException::withMessages([
                'lineas' => 'Selecciona al menos una unidad para cobrar.',
            ]);
        }

        return $unidades;
    }

    /**
     * Las opciones elegidas se reflejan en el documento **como detalle del concepto**, no como
     * línea propia (FR-052): una guarnición no es un artículo vendido aparte.
     */
    private function conceptoConOpciones(PosCuentaLinea $linea): string
    {
        $opciones = $linea->opciones->pluck('nombre')->filter()->all();

        return $opciones === []
            ? $linea->concepto
            : $linea->concepto.' ('.implode(', ', $opciones).')';
    }

    /**
     * Una opción con artículo vinculado consume stock aunque no genere línea de factura, así que
     * su movimiento hay que registrarlo a mano: la salida automática de `EmisorFacturas` va por
     * línea con `articulo_id`, y aquí no hay ninguna (research.md D5).
     *
     * Si el artículo vinculado no gestiona stock no se registra nada — no es un error, es el
     * mismo criterio que ya aplica el resto del sistema. `movimientos_stock` es append-only.
     */
    private function moverStockDeOpcionesVinculadas(PosCuentaLinea $linea, float $unidades, Factura $factura): void
    {
        foreach ($linea->opciones as $opcion) {
            $articulo = $opcion->articuloVinculado;

            if (! $articulo || $articulo->tipo !== TipoArticulo::Producto || ! $articulo->gestion_stock) {
                continue;
            }

            $this->registroMovimientoStock->registrar(
                articulo: $articulo,
                tipo: TipoMovimientoStock::Salida,
                cantidad: $unidades,
                origen: OrigenMovimientoStock::Factura,
                motivo: "Opción «{$opcion->nombre}» de «{$linea->concepto}»",
                factura: $factura,
            );
        }
    }

    /** @return array<string, mixed>|null */
    private function receptorDeLaCuenta(PosCuenta $cuenta): ?array
    {
        if (! $cuenta->cliente_nif) {
            return null;
        }

        return [
            'cliente_id' => $cuenta->cliente_id,
            'cliente_nif' => $cuenta->cliente_nif,
            'cliente_nombre' => $cuenta->cliente_nombre,
            'cliente_razon_social' => $cuenta->cliente_razon_social,
            'cliente_direccion' => $cuenta->cliente_direccion,
        ];
    }
}
