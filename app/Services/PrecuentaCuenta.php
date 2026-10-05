<?php

namespace App\Services;

use App\Exceptions\CuentaVersionDesfasadaException;
use App\Models\Cliente;
use App\Models\PosCuenta;
use App\Models\PosCuentaLinea;
use App\Models\PosPrecuenta;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Precuenta de una cuenta abierta (feature 049): documento **no fiscal** con lo pendiente, para que
 * el cliente lo revise antes de pagar. No pasa por {@see RegistroTicket}: no crea factura, no toca
 * series ni Verifactu, ni stock, ni cobros (docs/02-facturacion-espana.md §3.2).
 *
 * Tres decisiones que gobiernan este servicio:
 *
 * - **Un solo cálculo del importe** (research D2): las líneas salen de
 *   {@see CobradorCuenta::lineasACobrar()} y el total de la misma {@see CalculadoraFactura}, con el
 *   régimen del tenant y el recargo del receptor que aplicaría el cobro. Así el total coincide al
 *   céntimo con el ticket, y una divergencia futura entre los dos caminos es imposible.
 * - **El estado se deriva por huella, no se guarda** (research D4): la cuenta no tiene ningún flag
 *   "precuenta vigente". Se compara la huella del consumo actual con la de la última precuenta, de
 *   modo que cualquier camino que cambie el importe (hoy o en el futuro) la desactualiza solo.
 * - **Append-only con foto**: cada emisión es una fila nueva con lo impreso (research D3).
 */
class PrecuentaCuenta
{
    public const ESTADO_NINGUNA = 'ninguna';

    public const ESTADO_VIGENTE = 'vigente';

    public const ESTADO_DESACTUALIZADA = 'desactualizada';

    public function __construct(
        private readonly CobradorCuenta $cobrador,
        private readonly CalculadoraFactura $calculadora,
    ) {}

    /**
     * SHA-256 del consumo: lo que decide el importe, sin lo cobrado. Las líneas se ordenan por su
     * propio contenido y no por id porque el guardado del TPV puede recrearlas con ids nuevos.
     */
    public function huellaConsumo(PosCuenta $cuenta, ?bool $suplementoActivo = null): string
    {
        return $this->huella($cuenta, conSaldado: false, suplementoActivo: $suplementoActivo);
    }

    /** Huella del consumo + lo ya cobrado por línea: decide si una emisión es "reimpresión". */
    public function huellaPendiente(PosCuenta $cuenta): string
    {
        return $this->huella($cuenta, conSaldado: true);
    }

    /**
     * @param  bool|null  $suplementoActivo  el flag de suplemento de zona ya leído, si quien llama
     *                                       evalúa muchas cuentas a la vez (la Sala); null = leerlo.
     */
    public function estado(PosCuenta $cuenta, ?PosPrecuenta $ultima, ?bool $suplementoActivo = null): string
    {
        if ($ultima === null) {
            return self::ESTADO_NINGUNA;
        }

        return hash_equals($ultima->huella_consumo, $this->huellaConsumo($cuenta, $suplementoActivo))
            ? self::ESTADO_VIGENTE
            : self::ESTADO_DESACTUALIZADA;
    }

    /**
     * Líneas (foto imprimible) y total de lo pendiente, calculados como los calcularía el cobro.
     *
     * Las líneas se muestran **sin** el suplemento de zona y el suplemento va como concepto propio
     * (§ "Suplemento de zona: nunca un aumento silencioso"): su importe es la diferencia exacta
     * entre el total real y el total sin suplemento, así que líneas + suplemento suman el total al
     * céntimo.
     *
     * @return array{lineas: list<array{concepto: string, opciones: list<string>, cantidad: string, precio_unitario: string, importe: string}>, total: float, suplemento: float, importe_suplemento: float}
     */
    public function calcular(PosCuenta $cuenta): array
    {
        $cuenta->loadMissing('lineas.opciones', 'mesa.zona');

        $pendientes = $cuenta->lineas->filter(fn (PosCuentaLinea $l) => $l->cantidadPendiente() > 0)->values();
        $unidades = $pendientes->mapWithKeys(fn (PosCuentaLinea $l) => [$l->id => $l->cantidadPendiente()])->all();

        $suplemento = $this->cobrador->suplementoEfectivo($cuenta);
        $aplicaRecargo = $this->aplicaRecargo($cuenta);

        $real = $this->calcularLineas($cuenta, $unidades, $suplemento, $aplicaRecargo);
        $sinSuplemento = $suplemento > 0
            ? $this->calcularLineas($cuenta, $unidades, 0.0, $aplicaRecargo)
            : $real;

        $total = round($real->total, 2);

        $lineas = [];
        $sumaLineas = 0.0;

        foreach ($pendientes as $i => $linea) {
            $calculo = $sinSuplemento->lineas[$i];
            $importe = round($calculo['base'] + $calculo['cuotaImpuesto'] + $calculo['cuotaRecargo'], 2);
            $cantidad = $linea->cantidadPendiente();
            $sumaLineas += $importe;

            $lineas[] = [
                'concepto' => $linea->concepto,
                'opciones' => $linea->opciones->pluck('nombre')->filter()->values()->all(),
                'cantidad' => number_format($cantidad, 2, '.', ''),
                // Informativo: el importe de la línea es el que manda (sale de la calculadora).
                'precio_unitario' => number_format(round($importe / $cantidad, 2), 2, '.', ''),
                'importe' => number_format($importe, 2, '.', ''),
            ];
        }

        return [
            'lineas' => $lineas,
            'total' => $total,
            'suplemento' => $suplemento,
            'importe_suplemento' => round($total - $sumaLineas, 2),
        ];
    }

    /**
     * Emite una precuenta de toda la cuenta pendiente. Revalida bajo bloqueo de la cuenta: estado
     * abierto, versión (mismo bloqueo optimista que el guardado) y pendiente > 0.
     *
     * @throws CuentaVersionDesfasadaException
     * @throws ValidationException
     */
    public function emitir(PosCuenta $cuenta, int $version, ?int $usuarioId): PosPrecuenta
    {
        return DB::transaction(function () use ($cuenta, $version, $usuarioId) {
            $cuenta = PosCuenta::query()->lockForUpdate()->findOrFail($cuenta->id);
            $cuenta->load('lineas.opciones', 'mesa.zona');

            if (! $cuenta->estaAbierta()) {
                throw ValidationException::withMessages(['cuenta' => __('Esta cuenta ya no está abierta.')]);
            }

            if ($version !== (int) $cuenta->version) {
                throw CuentaVersionDesfasadaException::paraCuenta($cuenta->id);
            }

            if ($cuenta->pendiente() <= 0) {
                throw ValidationException::withMessages(['cuenta' => __('No hay nada pendiente para la precuenta.')]);
            }

            $calculo = $this->calcular($cuenta);
            $huellaPendiente = $this->huellaPendiente($cuenta);

            $ultima = $cuenta->precuentas()->reorder()->latest('id')->first();

            return PosPrecuenta::create([
                'tenant_id' => $cuenta->tenant_id,
                'cuenta_id' => $cuenta->id,
                'mesa_id' => $cuenta->mesa_id,
                'mesa_nombre' => $cuenta->mesa?->nombre,
                'zona_nombre' => $cuenta->mesa?->zona?->nombre,
                'usuario_id' => $usuarioId,
                'emitida_en' => now(),
                'cuenta_version' => (int) $cuenta->version,
                'huella_consumo' => $this->huellaConsumo($cuenta),
                'huella_pendiente' => $huellaPendiente,
                // Nada cambió desde la anterior, ni el consumo ni lo cobrado (FR-025).
                'reimpresion' => $ultima !== null && hash_equals($ultima->huella_pendiente, $huellaPendiente),
                'regimen_impositivo' => tenant()->regimen_impositivo->value,
                'suplemento_zona' => $calculo['suplemento'],
                'comensales' => $cuenta->comensales,
                'lineas' => $calculo['lineas'],
                'total' => $calculo['total'],
            ]);
        });
    }

    /**
     * Bloque `precuenta` del payload de la cuenta: estado derivado, última emisión y, solo si está
     * desactualizada, el total actual que se cobraría (lo muestra el aviso del cobro, FR-024).
     *
     * @return array{estado: string, ultima: array<string, mixed>|null, total_actual: string|null}
     */
    public function resumen(PosCuenta $cuenta, ?PosPrecuenta $ultima): array
    {
        $estado = $this->estado($cuenta, $ultima);

        return [
            'estado' => $estado,
            'ultima' => $ultima === null ? null : $this->datosEmision($ultima),
            'total_actual' => $estado === self::ESTADO_DESACTUALIZADA && $cuenta->estaAbierta()
                ? number_format($this->calcular($cuenta)['total'], 2, '.', '')
                : null,
        ];
    }

    /** @return array{id: int, total: string, emitida_en: string|null, reimpresion: bool, pdf_url: string} */
    public function datosEmision(PosPrecuenta $precuenta): array
    {
        return [
            'id' => $precuenta->id,
            'total' => number_format((float) $precuenta->total, 2, '.', ''),
            'emitida_en' => $precuenta->emitida_en?->toIso8601String(),
            'reimpresion' => (bool) $precuenta->reimpresion,
            'pdf_url' => route('pos.precuentas.pdf', ['precuenta' => $precuenta->id], false),
        ];
    }

    // ── Internos ─────────────────────────────────────────────────────────

    /** @param  array<int, float>  $unidades */
    private function calcularLineas(PosCuenta $cuenta, array $unidades, float $suplemento, bool $aplicaRecargo): ResultadoCalculoFactura
    {
        $lineas = $this->cobrador->lineasACobrar($cuenta, $unidades, $suplemento);

        return $this->calculadora->calcular(
            regimen: tenant()->regimen_impositivo,
            aplicaRecargo: $aplicaRecargo,
            irpfPorcentaje: null,
            lineas: array_map(fn (array $l) => [
                'cantidad' => (float) $l['cantidad'],
                'precioUnitario' => (float) $l['precio_unitario'],
                'descuentoPorcentaje' => null,
                'tipoImpositivo' => (float) $l['tipo_impositivo'],
            ], $lineas),
        );
    }

    /**
     * Mismo criterio que {@see RegistroTicket::registrar()} sobre el receptor que el cobro le pasa
     * (el de la cuenta, si tiene NIF): recargo solo si el cliente identificado lo tiene marcado.
     */
    private function aplicaRecargo(PosCuenta $cuenta): bool
    {
        if (! $cuenta->cliente_nif || ! $cuenta->cliente_id) {
            return false;
        }

        return (bool) (Cliente::query()->find($cuenta->cliente_id)?->aplica_recargo_equivalencia ?? false);
    }

    private function huella(PosCuenta $cuenta, bool $conSaldado, ?bool $suplementoActivo = null): string
    {
        $cuenta->loadMissing('lineas.opciones', 'mesa.zona');

        $dec = fn ($v) => number_format((float) $v, 2, '.', '');

        $lineas = $cuenta->lineas->map(function (PosCuentaLinea $l) use ($dec, $conSaldado) {
            $opciones = $l->opciones->pluck('opcion_id')->map(fn ($id) => (int) $id)->sort()->values()->all();

            $fila = [
                $l->articulo_id === null ? null : (int) $l->articulo_id,
                (string) $l->concepto,
                $dec($l->cantidad),
                $dec($l->precio_unitario),
                $dec($l->suplemento_opciones),
                $dec($l->tipo_impositivo),
                $opciones,
            ];

            if ($conSaldado) {
                $fila[] = $dec($l->cantidad_saldada);
            }

            return json_encode($fila, JSON_UNESCAPED_UNICODE);
        })->sort()->values()->all();

        return hash('sha256', json_encode([
            'lineas' => $lineas,
            'suplemento_zona' => $dec($this->cobrador->suplementoEfectivo($cuenta, $suplementoActivo)),
        ], JSON_UNESCAPED_UNICODE));
    }
}
