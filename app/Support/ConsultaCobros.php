<?php

namespace App\Support;

use App\Enums\TipoFactura;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Único espejo en SQL de las reglas de cobro de App\Models\Factura (feature 043, research D2).
 *
 * Los métodos del modelo (`totalCobrable()`, `montoCobrado()`, `saldoPendiente()`,
 * `estadoCobro()`, `admiteCobros()`) siguen siendo la fuente de verdad conceptual: esta clase
 * traduce esas mismas reglas a expresiones SQL para poder filtrar, ordenar y paginar en el
 * servidor sobre miles de facturas (SC-004), algo que cargar cada fila en PHP no permite.
 *
 * Blindaje: `tests/Unit/ConsultaCobrosParidadTest.php` compara, fila a fila, el resultado de esta
 * clase contra los métodos del modelo para un dataset mixto. Si alguien cambia la regla en el
 * modelo y no aquí, ese test se pone en rojo. No añadir aquí ninguna regla que no exista ya en
 * `Factura` (FR-004: cero lógica de negocio nueva).
 */
class ConsultaCobros
{
    /**
     * Lista blanca de columnas ordenables del listado (FR-012, T026). Cualquier columna fuera
     * de aquí cae al orden por defecto (fecha de vencimiento asc, nulos al final — patrón
     * LogActividadController).
     *
     * @var array<string, string>
     */
    public const COLUMNAS_ORDENABLES = [
        'fecha_expedicion' => 'facturas.fecha_expedicion',
        'fecha_vencimiento' => 'facturas.fecha_vencimiento',
        'total_cobrable' => 'total_cobrable',
        'monto_cobrado' => 'cobrado',
        'saldo_pendiente' => 'saldo_pendiente',
        'dias_retraso' => 'dias_retraso',
        'cliente' => 'cliente_orden',
    ];

    /**
     * Construye el Builder base: facturas que admiten cobro del tenant indicado, con las
     * expresiones derivadas (`cobrado`, `total_cobrable`, `saldo_pendiente`, `estado_cobro`,
     * `vencida`, `dias_retraso`) ya seleccionadas como columnas.
     *
     * Filtro explícito por tenant además del global scope de BelongsToTenant (Principio I,
     * memoria project_tenant_route_binding): se aplica aquí, antes de cualquier filtro/orden.
     */
    /**
     * Expresiones SQL derivadas, compartidas por {@see self::query()} y {@see self::aplicarFiltros()}
     * (esta última no puede usar HAVING sobre los alias de SELECT: SQLite, a diferencia de
     * MySQL/MariaDB, lo prohíbe fuera de una consulta con GROUP BY/agregados).
     *
     * @return array{cobrado: string, totalCobrable: string, saldo: string, vencida: string, diasRetraso: string, estadoCobro: string}
     */
    private static function expresiones(): array
    {
        $driver = DB::connection()->getDriverName();

        // COALESCE(SUM(...), 0) de los pagos vigentes de la factura — espejo de Factura::montoCobrado().
        $subCobrado = '(SELECT COALESCE(SUM(p.importe), 0) FROM pagos p '
            .'WHERE p.factura_id = facturas.id AND p.anulado_at IS NULL AND p.tenant_id = facturas.tenant_id)';

        // Importe efectivo a cobrar — espejo de Factura::totalCobrable(): sustitución usa el total
        // de la rectificativa emitida; diferencias suma ambos; el resto usa su propio total.
        $totalCobrableExpr = "(CASE
                WHEN facturas.estado = 'rectificada' AND r.id IS NOT NULL AND r.tipo_rectificacion = 'sustitucion' THEN r.total
                WHEN facturas.estado = 'rectificada' AND r.id IS NOT NULL AND r.tipo_rectificacion = 'diferencias' THEN facturas.total + r.total
                ELSE facturas.total
            END)";

        $saldoExpr = "({$totalCobrableExpr} - {$subCobrado})";

        // DATEDIFF en MySQL/MariaDB; JULIANDAY en SQLite (usado por la suite de tests).
        $diasRetrasoExpr = $driver === 'sqlite'
            ? 'CAST(JULIANDAY(CURRENT_DATE) - JULIANDAY(facturas.fecha_vencimiento) AS INTEGER)'
            : 'DATEDIFF(CURDATE(), facturas.fecha_vencimiento)';

        $hoyExpr = $driver === 'sqlite' ? "DATE('now')" : 'CURDATE()';

        $vencidaExpr = '(CASE WHEN facturas.fecha_vencimiento IS NOT NULL '
            ."AND facturas.fecha_vencimiento < {$hoyExpr} AND {$saldoExpr} > 0 THEN 1 ELSE 0 END)";

        $estadoCobroExpr = "(CASE
                WHEN {$subCobrado} <= 0 THEN 'pendiente'
                WHEN {$subCobrado} >= {$totalCobrableExpr} THEN 'cobrada'
                ELSE 'parcial'
            END)";

        return [
            'cobrado' => $subCobrado,
            'totalCobrable' => $totalCobrableExpr,
            'saldo' => $saldoExpr,
            'vencida' => $vencidaExpr,
            'diasRetraso' => $diasRetrasoExpr,
            'estadoCobro' => $estadoCobroExpr,
        ];
    }

    public static function query(int|string $tenantId): Builder
    {
        $e = self::expresiones();
        $subCobrado = $e['cobrado'];
        $totalCobrableExpr = $e['totalCobrable'];
        $saldoExpr = $e['saldo'];
        $vencidaExpr = $e['vencida'];
        $diasRetrasoExpr = $e['diasRetraso'];
        $estadoCobroExpr = $e['estadoCobro'];

        $query = \App\Models\Factura::query()
            ->from('facturas')
            ->leftJoin('facturas as r', function ($join) {
                $join->on('r.factura_rectificada_id', '=', 'facturas.id')
                    ->where('r.estado', '=', 'emitida')
                    ->whereNull('r.deleted_at');
            })
            ->leftJoin('clientes', 'clientes.id', '=', 'facturas.cliente_id')
            ->leftJoin('series', 'series.id', '=', 'facturas.serie_id')
            ->where('facturas.tenant_id', $tenantId)
            ->whereNull('facturas.deleted_at')
            ->where('facturas.tipo', '!=', TipoFactura::Simplificada->value)
            ->where('facturas.es_rectificativa', false)
            ->where(function (Builder $q) {
                $q->where('facturas.estado', 'emitida')
                    ->orWhere(function (Builder $q2) {
                        $q2->where('facturas.estado', 'rectificada')->whereNotNull('r.id');
                    });
            })
            ->selectRaw("facturas.*, {$subCobrado} as cobrado")
            ->selectRaw("{$totalCobrableExpr} as total_cobrable")
            ->selectRaw("{$saldoExpr} as saldo_pendiente")
            ->selectRaw("{$estadoCobroExpr} as estado_cobro")
            ->selectRaw("{$vencidaExpr} as vencida")
            ->selectRaw("(CASE WHEN {$vencidaExpr} = 1 THEN {$diasRetrasoExpr} ELSE NULL END) as dias_retraso")
            ->selectRaw('r.id as rectificativa_id, r.tipo_rectificacion as modalidad_rectificacion, r.total as rectificativa_total')
            ->selectRaw('clientes.nombre as cliente_nombre, clientes.razon_social as cliente_razon_social')
            ->selectRaw('series.codigo as serie_codigo')
            ->selectRaw('COALESCE(NULLIF(clientes.razon_social, \'\'), clientes.nombre) as cliente_orden');

        return $query;
    }

    /**
     * FR-011/research D10: filtros combinables con AND. `solo_vencidas` es un control aparte del
     * selector de estado de cobro, nunca un cuarto valor de ese selector.
     *
     * @param  array{estado_cobro?: ?string, cliente_id?: ?int, serie_id?: ?int, solo_vencidas?: bool, desde?: ?string, hasta?: ?string}  $filtros
     */
    public static function aplicarFiltros(Builder $query, array $filtros): Builder
    {
        $e = self::expresiones();

        if (! empty($filtros['estado_cobro'])) {
            $query->whereRaw("({$e['estadoCobro']}) = ?", [$filtros['estado_cobro']]);
        }

        if (! empty($filtros['cliente_id'])) {
            $query->where('facturas.cliente_id', $filtros['cliente_id']);
        }

        if (! empty($filtros['serie_id'])) {
            $query->where('facturas.serie_id', $filtros['serie_id']);
        }

        if (! empty($filtros['solo_vencidas'])) {
            $query->whereRaw("({$e['vencida']}) = 1");
        }

        if (! empty($filtros['desde'])) {
            $query->whereDate('facturas.fecha_expedicion', '>=', $filtros['desde']);
        }

        if (! empty($filtros['hasta'])) {
            $query->whereDate('facturas.fecha_expedicion', '<=', $filtros['hasta']);
        }

        return $query;
    }

    /**
     * FR-013/research D9: busca sobre el número de factura y sobre el nombre/razón social del
     * cliente (patrón LogActividadController).
     */
    public static function aplicarBusqueda(Builder $query, string $termino): Builder
    {
        $termino = trim($termino);

        if ($termino === '') {
            return $query;
        }

        return $query->where(function (Builder $q) use ($termino) {
            $q->where('facturas.numero_completo', 'like', "%{$termino}%")
                ->orWhere('clientes.nombre', 'like', "%{$termino}%")
                ->orWhere('clientes.razon_social', 'like', "%{$termino}%");
        });
    }

    /**
     * FR-012/T026: solo columnas de la lista blanca; cualquier otra cae al orden por defecto.
     */
    public static function aplicarOrden(Builder $query, ?string $columna, string $direccion): Builder
    {
        $direccion = strtolower($direccion) === 'desc' ? 'desc' : 'asc';
        $columnaBd = self::COLUMNAS_ORDENABLES[$columna] ?? 'facturas.fecha_vencimiento';

        return $query
            ->orderByRaw("({$columnaBd} IS NULL) asc")
            ->orderBy(DB::raw($columnaBd), $direccion);
    }
}
