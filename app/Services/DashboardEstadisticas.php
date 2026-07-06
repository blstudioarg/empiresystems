<?php

namespace App\Services;

use App\Enums\EstadoCompra;
use App\Enums\EstadoFactura;
use App\Enums\TipoRectificacion;
use App\Models\Articulo;
use App\Models\Compra;
use App\Models\Factura;
use App\Models\Pago;
use App\Support\RangoFechas;
use App\Support\VariacionPorcentual;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class DashboardEstadisticas
{
    /**
     * Estados de factura que cuentan como facturación real (excluye borrador/anulada).
     */
    private const ESTADOS_FACTURADOS = [
        EstadoFactura::Emitida,
        EstadoFactura::Pagada,
        EstadoFactura::Vencida,
        EstadoFactura::Rectificada,
    ];

    public function resumen(RangoFechas $rango): array
    {
        return [
            'rango' => [
                'preset' => $rango->preset->value,
                'desde' => $rango->desde->toDateString(),
                'hasta' => $rango->hasta->toDateString(),
                'etiqueta' => $rango->etiqueta(),
            ],
            'kpis' => $this->kpis($rango),
            'impuestos' => $this->impuestos($rango),
            'serie_facturacion' => $this->serieFacturacion($rango),
            'comparativo' => $this->comparativo($rango),
            'distribucion_estados' => $this->distribucionEstados($rango),
            'top_clientes' => $this->topClientes($rango),
            'alertas_stock' => $this->alertasStock(),
            'facturas_recientes' => $this->facturasRecientes($rango),
        ];
    }

    /**
     * Acota una columna de fecha (sin hora) a `[inicio, fin]` inclusive. Usa `< fin + 1 día` en
     * vez de `<= fin` porque `fecha_expedicion`/`fecha` se guardan como datetime a medianoche
     * (`Y-m-d 00:00:00`, incluso siendo columnas `date`): un `whereBetween` con el string
     * `Y-m-d` de `fin` como límite superior excluye por comparación de string cualquier registro
     * fechado exactamente ese día.
     */
    private function acotarFecha($query, string $columna, Carbon $inicio, Carbon $fin)
    {
        return $query
            ->where($columna, '>=', $inicio->toDateString())
            ->where($columna, '<', $fin->copy()->addDay()->toDateString());
    }

    /**
     * Facturas facturables (no simplificadas, no rectificativas, en estado de facturación) con
     * `fecha_expedicion` en `[inicio, fin]`, con eager load de `rectificativa` para poder
     * calcular `totalCobrable()` sin N+1.
     */
    private function facturasFacturablesEnRango(Carbon $inicio, Carbon $fin): Collection
    {
        return $this->acotarFecha(
            Factura::query()
                ->where('tipo', '!=', 'simplificada')
                ->where('es_rectificativa', false)
                ->whereIn('estado', array_map(fn (EstadoFactura $e) => $e->value, self::ESTADOS_FACTURADOS)),
            'fecha_expedicion',
            $inicio,
            $fin,
        )
            ->with('rectificativa')
            ->get();
    }

    private function kpis(RangoFechas $rango): array
    {
        $rangoAnterior = $rango->anterior();

        $actual = $this->resumenMensual($rango->desde, $rango->hasta);
        $anterior = $this->resumenMensual($rangoAnterior->desde, $rangoAnterior->hasta);
        $gastos = $this->gastosEnRango($rango->desde, $rango->hasta);

        return [
            'facturado' => [
                'valor' => $actual['total_facturado'],
                'variacion_pct' => VariacionPorcentual::calcular($actual['total_facturado'], $anterior['total_facturado']),
            ],
            'cobrado' => [
                'valor' => $actual['total_cobrado'],
                'variacion_pct' => VariacionPorcentual::calcular($actual['total_cobrado'], $anterior['total_cobrado']),
            ],
            'pendiente_cobro' => [
                'valor' => $this->pendienteCobro(),
            ],
            'num_facturas' => [
                'valor' => $actual['num_facturas'],
                'variacion_pct' => VariacionPorcentual::calcular($actual['num_facturas'], $anterior['num_facturas']),
            ],
            'gastos' => [
                'valor' => $gastos,
            ],
            'resultado' => [
                'valor' => round($actual['total_facturado'] - $gastos, 2),
            ],
            'ventas_pos' => [
                'valor' => $this->ventasPosEnRango($rango->desde, $rango->hasta),
            ],
        ];
    }

    private function comprasConfirmadasQuery(Carbon $inicio, Carbon $fin)
    {
        return $this->acotarFecha(
            Compra::query()->where('estado', EstadoCompra::Confirmada->value),
            'fecha',
            $inicio,
            $fin,
        );
    }

    private function gastosEnRango(Carbon $inicio, Carbon $fin): float
    {
        return round((float) $this->comprasConfirmadasQuery($inicio, $fin)->sum('total'), 2);
    }

    private function ivaSoportadoEnRango(Carbon $inicio, Carbon $fin): float
    {
        return round((float) $this->comprasConfirmadasQuery($inicio, $fin)->sum('cuota_impuesto_total'), 2);
    }

    private function ventasPosEnRango(Carbon $inicio, Carbon $fin): float
    {
        return round((float) $this->acotarFecha(
            Factura::query()->where('tipo', 'simplificada'),
            'fecha_expedicion',
            $inicio,
            $fin,
        )->sum('total'), 2);
    }

    /**
     * IVA repercutido neto de una factura facturable, con el mismo neteo de rectificativas que
     * `Factura::totalCobrable()` pero sobre `cuota_impuesto_total` en vez de `total`.
     */
    private function cuotaImpuestoNeta(Factura $factura): float
    {
        $rectificativa = ($factura->estado === EstadoFactura::Rectificada) ? $factura->rectificativaEmitida() : null;

        if ($rectificativa === null) {
            return round((float) $factura->cuota_impuesto_total, 2);
        }

        if ($rectificativa->tipo_rectificacion === TipoRectificacion::Sustitucion) {
            return round((float) $rectificativa->cuota_impuesto_total, 2);
        }

        return round((float) $factura->cuota_impuesto_total + (float) $rectificativa->cuota_impuesto_total, 2);
    }

    private function impuestos(RangoFechas $rango): array
    {
        $facturas = $this->facturasFacturablesEnRango($rango->desde, $rango->hasta);

        return [
            'repercutido' => round((float) $facturas->sum(fn (Factura $f) => $this->cuotaImpuestoNeta($f)), 2),
            'soportado' => $this->ivaSoportadoEnRango($rango->desde, $rango->hasta),
            'etiqueta' => tenant()->regimen_impositivo->label(),
        ];
    }

    private function resumenMensual(Carbon $inicio, Carbon $fin): array
    {
        $facturas = $this->facturasFacturablesEnRango($inicio, $fin);

        $cobrado = $this->acotarFecha(
            Pago::query()->whereNull('anulado_at'),
            'fecha',
            $inicio,
            $fin,
        )->sum('importe');

        return [
            'total_facturado' => round((float) $facturas->sum(fn (Factura $f) => $f->totalCobrable()), 2),
            'num_facturas' => $facturas->count(),
            'total_cobrado' => round((float) $cobrado, 2),
        ];
    }

    private function pendienteCobro(): float
    {
        $facturas = $this->facturasQuery()
            ->whereIn('estado', [EstadoFactura::Emitida->value, EstadoFactura::Vencida->value])
            ->get(['id', 'total']);

        $pendiente = 0.0;

        foreach ($facturas as $factura) {
            $saldo = $factura->saldoPendiente();

            if ($saldo > 0) {
                $pendiente += $saldo;
            }
        }

        return round($pendiente, 2);
    }

    private function facturasQuery()
    {
        return Factura::query()
            ->where('tipo', '!=', 'simplificada')
            ->whereIn('estado', array_map(fn (EstadoFactura $e) => $e->value, self::ESTADOS_FACTURADOS));
    }

    /**
     * Divide el rango en sub-periodos para series/comparativos: un bucket por día si el rango es
     * corto (`RangoFechas::granularidad() === 'dia'`), o un bucket por mes (recortado a los
     * límites del rango) si es largo. Evita cientos de puntos diarios en un rango de un año.
     *
     * @return list<array{inicio: Carbon, fin: Carbon, etiqueta: string}>
     */
    private function bucketsDelRango(RangoFechas $rango): array
    {
        return $rango->granularidad() === 'dia'
            ? $this->bucketsDiarios($rango)
            : $this->bucketsMensuales($rango);
    }

    private function bucketsDiarios(RangoFechas $rango): array
    {
        $buckets = [];
        $cursor = $rango->desde->copy();

        while ($cursor->lte($rango->hasta)) {
            $buckets[] = [
                'inicio' => $cursor->copy(),
                'fin' => $cursor->copy(),
                'etiqueta' => $cursor->translatedFormat('d M'),
            ];
            $cursor->addDay();
        }

        return $buckets;
    }

    private function bucketsMensuales(RangoFechas $rango): array
    {
        $buckets = [];
        $cursor = $rango->desde->copy()->startOfMonth();

        while ($cursor->lte($rango->hasta)) {
            $inicio = $cursor->copy()->max($rango->desde);
            $fin = $cursor->copy()->endOfMonth()->min($rango->hasta);

            $buckets[] = [
                'inicio' => $inicio,
                'fin' => $fin,
                'etiqueta' => $cursor->translatedFormat('M Y'),
            ];
            $cursor->addMonthNoOverflow();
        }

        return $buckets;
    }

    private function serieFacturacion(RangoFechas $rango): array
    {
        return collect($this->bucketsDelRango($rango))
            ->map(function (array $bucket) {
                $resumen = $this->resumenMensual($bucket['inicio'], $bucket['fin']);

                return [
                    'etiqueta' => $bucket['etiqueta'],
                    'facturado' => $resumen['total_facturado'],
                ];
            })
            ->all();
    }

    private function comparativo(RangoFechas $rango): array
    {
        return collect($this->bucketsDelRango($rango))
            ->map(function (array $bucket) {
                $resumen = $this->resumenMensual($bucket['inicio'], $bucket['fin']);

                return [
                    'etiqueta' => $bucket['etiqueta'],
                    'facturado' => $resumen['total_facturado'],
                    'cobrado' => $resumen['total_cobrado'],
                ];
            })
            ->all();
    }

    private function distribucionEstados(RangoFechas $rango): array
    {
        $conteos = $this->acotarFecha(
            Factura::query()->where('tipo', '!=', 'simplificada'),
            'fecha_expedicion',
            $rango->desde,
            $rango->hasta,
        )
            ->selectRaw('estado, COUNT(*) as cantidad')
            ->groupBy('estado')
            ->pluck('cantidad', 'estado');

        return collect(EstadoFactura::cases())
            ->map(fn (EstadoFactura $estado) => [
                'estado' => $estado->value,
                'cantidad' => (int) ($conteos[$estado->value] ?? 0),
            ])
            ->all();
    }

    private function topClientes(RangoFechas $rango): array
    {
        $facturas = $this->facturasFacturablesEnRango($rango->desde, $rango->hasta);

        return $facturas
            ->groupBy('cliente_id')
            ->map(function (Collection $facturasCliente) {
                $primera = $facturasCliente->first();

                return [
                    'cliente_id' => $primera->cliente_id,
                    'nombre' => $primera->cliente_razon_social ?: ($primera->cliente_nombre ?: 'Sin nombre'),
                    'total_facturado' => round((float) $facturasCliente->sum(fn (Factura $f) => $f->totalCobrable()), 2),
                ];
            })
            ->sortByDesc('total_facturado')
            ->take(5)
            ->values()
            ->all();
    }

    private function alertasStock(): array
    {
        $gestionaStock = Articulo::query()->where('gestion_stock', true)->exists();

        $items = Articulo::query()
            ->where('gestion_stock', true)
            ->where(function ($query) {
                $query->whereColumn('stock_actual', '<', 'stock_minimo')
                    ->orWhere('stock_actual', '<', 0);
            })
            ->get(['id', 'nombre', 'stock_actual', 'stock_minimo'])
            ->map(fn (Articulo $articulo) => [
                'articulo_id' => $articulo->id,
                'nombre' => $articulo->nombre,
                'stock_actual' => (float) $articulo->stock_actual,
                'stock_minimo' => $articulo->stock_minimo !== null ? (float) $articulo->stock_minimo : null,
            ])
            ->all();

        return [
            'gestiona_stock' => $gestionaStock,
            'items' => $items,
        ];
    }

    private function facturasRecientes(RangoFechas $rango): array
    {
        return $this->acotarFecha(
            Factura::query()->where('tipo', '!=', 'simplificada'),
            'fecha_expedicion',
            $rango->desde,
            $rango->hasta,
        )
            ->orderByDesc('fecha_expedicion')
            ->limit(8)
            ->get(['id', 'numero_completo', 'estado', 'cliente_nombre', 'cliente_razon_social', 'total', 'fecha_expedicion'])
            ->map(fn (Factura $factura) => [
                'id' => $factura->id,
                'numero_completo' => $factura->numero_completo ?? '—',
                'estado' => $factura->estado->value,
                'cliente_nombre' => $factura->cliente_razon_social ?: ($factura->cliente_nombre ?: 'Sin nombre'),
                'total' => round((float) $factura->total, 2),
                'fecha_expedicion' => $factura->fecha_expedicion->format('Y-m-d'),
            ])
            ->all();
    }
}
