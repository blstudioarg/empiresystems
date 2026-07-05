<?php

namespace App\Services;

use App\Enums\EstadoFactura;
use App\Models\Articulo;
use App\Models\Factura;
use App\Models\Pago;
use App\Support\VariacionPorcentual;
use Carbon\Carbon;

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

    public function resumen(?Carbon $ahora = null): array
    {
        $ahora = $ahora ?? now();

        return [
            'kpis' => $this->kpis($ahora),
            'serie_facturacion_12_meses' => $this->serieFacturacion(12, $ahora),
            'comparativo_6_meses' => $this->comparativo(6, $ahora),
            'distribucion_estados' => $this->distribucionEstados(),
            'top_clientes' => $this->topClientes(),
            'alertas_stock' => $this->alertasStock(),
            'facturas_recientes' => $this->facturasRecientes(),
        ];
    }

    private function facturasQuery()
    {
        return Factura::query()
            ->where('tipo', '!=', 'simplificada')
            ->whereIn('estado', array_map(fn (EstadoFactura $e) => $e->value, self::ESTADOS_FACTURADOS));
    }

    private function kpis(Carbon $ahora): array
    {
        $inicioMesActual = $ahora->copy()->startOfMonth();
        $finMesActual = $ahora->copy()->endOfMonth();
        $inicioMesAnterior = $ahora->copy()->subMonthNoOverflow()->startOfMonth();
        $finMesAnterior = $ahora->copy()->subMonthNoOverflow()->endOfMonth();

        $actual = $this->resumenMensual($inicioMesActual, $finMesActual);
        $anterior = $this->resumenMensual($inicioMesAnterior, $finMesAnterior);

        return [
            'facturado_mes' => [
                'valor' => $actual['total_facturado'],
                'variacion_pct' => VariacionPorcentual::calcular($actual['total_facturado'], $anterior['total_facturado']),
            ],
            'cobrado_mes' => [
                'valor' => $actual['total_cobrado'],
                'variacion_pct' => VariacionPorcentual::calcular($actual['total_cobrado'], $anterior['total_cobrado']),
            ],
            'pendiente_cobro' => [
                'valor' => $this->pendienteCobro(),
            ],
            'num_facturas_mes' => [
                'valor' => $actual['num_facturas'],
                'variacion_pct' => VariacionPorcentual::calcular($actual['num_facturas'], $anterior['num_facturas']),
            ],
        ];
    }

    private function resumenMensual(Carbon $inicio, Carbon $fin): array
    {
        $facturado = $this->facturasQuery()
            ->whereBetween('fecha_expedicion', [$inicio->toDateString(), $fin->toDateString()])
            ->selectRaw('COALESCE(SUM(total), 0) as total, COUNT(*) as cantidad')
            ->first();

        $cobrado = Pago::query()
            ->whereNull('anulado_at')
            ->whereBetween('fecha', [$inicio->toDateString(), $fin->toDateString()])
            ->sum('importe');

        return [
            'total_facturado' => round((float) $facturado->total, 2),
            'num_facturas' => (int) $facturado->cantidad,
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

    private function serieFacturacion(int $meses, Carbon $ahora): array
    {
        $serie = [];

        for ($i = $meses - 1; $i >= 0; $i--) {
            $mes = $ahora->copy()->subMonthsNoOverflow($i);
            $resumen = $this->resumenMensual($mes->copy()->startOfMonth(), $mes->copy()->endOfMonth());

            $serie[] = [
                'etiqueta' => $mes->translatedFormat('M Y'),
                'facturado' => $resumen['total_facturado'],
            ];
        }

        return $serie;
    }

    private function comparativo(int $meses, Carbon $ahora): array
    {
        $serie = [];

        for ($i = $meses - 1; $i >= 0; $i--) {
            $mes = $ahora->copy()->subMonthsNoOverflow($i);
            $resumen = $this->resumenMensual($mes->copy()->startOfMonth(), $mes->copy()->endOfMonth());

            $serie[] = [
                'etiqueta' => $mes->translatedFormat('M Y'),
                'facturado' => $resumen['total_facturado'],
                'cobrado' => $resumen['total_cobrado'],
            ];
        }

        return $serie;
    }

    private function distribucionEstados(): array
    {
        $conteos = Factura::query()
            ->where('tipo', '!=', 'simplificada')
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

    private function topClientes(): array
    {
        return $this->facturasQuery()
            ->selectRaw('cliente_id, cliente_razon_social, cliente_nombre, SUM(total) as total_facturado')
            ->groupBy('cliente_id', 'cliente_razon_social', 'cliente_nombre')
            ->orderByDesc('total_facturado')
            ->limit(5)
            ->get()
            ->map(fn ($fila) => [
                'cliente_id' => $fila->cliente_id,
                'nombre' => $fila->cliente_razon_social ?: ($fila->cliente_nombre ?: 'Sin nombre'),
                'total_facturado' => round((float) $fila->total_facturado, 2),
            ])
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

    private function facturasRecientes(): array
    {
        return Factura::query()
            ->where('tipo', '!=', 'simplificada')
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
