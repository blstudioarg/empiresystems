<?php

namespace App\Services;

use App\Enums\EstadoFactura;
use App\Enums\EstadoUsuario;
use App\Models\Factura;
use App\Models\LogActividad;
use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Agregados de la home del panel de Super Admin (037-super-admin-panel-aislado), espejo
 * estructural de {@see DashboardEstadisticas} pero en contexto central (sin tenant activo).
 * Solo recuentos e importes agregados por tenant (FR-019): nunca detalle de negocio. Sin caché
 * ni precálculo (FR-014): recalcula en cada carga sobre consultas agrupadas fijas.
 */
class EstadisticasTenants
{
    private const MESES_ABREVIADOS = [
        1 => 'ene', 2 => 'feb', 3 => 'mar', 4 => 'abr', 5 => 'may', 6 => 'jun',
        7 => 'jul', 8 => 'ago', 9 => 'sep', 10 => 'oct', 11 => 'nov', 12 => 'dic',
    ];

    private const LIMITE_ULTIMOS = 5;

    private const VENTANA_INACTIVIDAD_DIAS = 30;

    public function resumen(): array
    {
        $tenants = Tenant::with('domains')->get();

        return [
            'totales' => $this->totales($tenants),
            'serie_altas' => $this->serieAltas($tenants),
            'ultimos_tenants' => $this->ultimosTenants($tenants),
            'ranking_tamano' => $this->rankingTamano($tenants),
            'atencion' => $this->atencion($tenants),
        ];
    }

    /**
     * @param  Collection<int, Tenant>  $tenants
     */
    private function totales(Collection $tenants): array
    {
        $inicioMes = now()->startOfMonth();

        return [
            'tenants' => $tenants->count(),
            'activos' => $tenants->where('activo', true)->count(),
            'inactivos' => $tenants->where('activo', false)->count(),
            'altas_mes' => $tenants->filter(fn (Tenant $t) => $t->created_at->gte($inicioMes))->count(),
            'usuarios' => User::whereNotNull('tenant_id')->count(),
        ];
    }

    /**
     * Evolución de altas de los últimos 12 meses (incluido el mes en curso), con los meses sin
     * altas rellenados a cero en PHP (portabilidad MySQL/MariaDB, research.md D5).
     *
     * @param  Collection<int, Tenant>  $tenants
     */
    private function serieAltas(Collection $tenants): array
    {
        $ejeMeses = collect(range(11, 0))
            ->map(fn (int $i) => now()->copy()->subMonthsNoOverflow($i)->startOfMonth());

        $conteosPorMes = $tenants
            ->groupBy(fn (Tenant $t) => $t->created_at->format('Y-m'))
            ->map->count();

        return $ejeMeses
            ->map(fn (Carbon $mes) => [
                'etiqueta' => self::MESES_ABREVIADOS[$mes->month].' '.$mes->format('y'),
                'valor' => (int) ($conteosPorMes[$mes->format('Y-m')] ?? 0),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, Tenant>  $tenants
     */
    private function ultimosTenants(Collection $tenants): array
    {
        return $tenants
            ->sortByDesc('created_at')
            ->take(self::LIMITE_ULTIMOS)
            ->map(fn (Tenant $t) => [
                'id' => $t->id,
                'nombre' => $t->nombre_comercial,
                'dominio' => $t->domains->first()?->domain,
                'activo' => $t->activo,
                'alta' => $t->created_at->format('d/m/Y'),
                'gestion_url' => route('super_admin.tenants.index'),
            ])
            ->values()
            ->all();
    }

    /**
     * Ranking por tamaño (FR-013): usuarios y documentos de facturación emitidos por tenant,
     * agregados con una consulta agrupada por métrica y cruzados en memoria — nunca una consulta
     * por tenant dentro de un bucle (data-model.md).
     *
     * @param  Collection<int, Tenant>  $tenants
     */
    private function rankingTamano(Collection $tenants): array
    {
        $usuariosPorTenant = User::whereNotNull('tenant_id')
            ->selectRaw('tenant_id, count(*) as total')
            ->groupBy('tenant_id')
            ->pluck('total', 'tenant_id');

        $documentosPorTenant = Factura::query()
            ->where('estado', '!=', EstadoFactura::Borrador->value)
            ->selectRaw('tenant_id, count(*) as total')
            ->groupBy('tenant_id')
            ->pluck('total', 'tenant_id');

        return $tenants
            ->map(fn (Tenant $t) => [
                'id' => $t->id,
                'nombre' => $t->nombre_comercial,
                'usuarios' => (int) ($usuariosPorTenant[$t->id] ?? 0),
                'documentos' => (int) ($documentosPorTenant[$t->id] ?? 0),
            ])
            ->sort(fn (array $a, array $b) => [$b['documentos'], $b['usuarios']] <=> [$a['documentos'], $a['usuarios']])
            ->take(self::LIMITE_ULTIMOS)
            ->values()
            ->all();
    }

    /**
     * Tenants que requieren atención (US3): desactivado, sin usuarios que puedan entrar, o sin
     * actividad en los últimos 30 días. Un tenant puede aparecer con varios motivos a la vez.
     *
     * @param  Collection<int, Tenant>  $tenants
     */
    private function atencion(Collection $tenants): array
    {
        $usuariosAprobadosActivosPorTenant = User::whereNotNull('tenant_id')
            ->where('estado', EstadoUsuario::Aprobado->value)
            ->where('activo', true)
            ->selectRaw('tenant_id, count(*) as total')
            ->groupBy('tenant_id')
            ->pluck('total', 'tenant_id');

        $ultimaActividadPorTenant = LogActividad::query()
            ->selectRaw('tenant_id, max(ocurrido_at) as ultima')
            ->groupBy('tenant_id')
            ->pluck('ultima', 'tenant_id');

        $limiteInactividad = now()->subDays(self::VENTANA_INACTIVIDAD_DIAS);

        return $tenants
            ->map(function (Tenant $tenant) use ($usuariosAprobadosActivosPorTenant, $ultimaActividadPorTenant, $limiteInactividad) {
                $motivos = [];

                if (! $tenant->activo) {
                    $motivos[] = 'desactivado';
                }

                if ((int) ($usuariosAprobadosActivosPorTenant[$tenant->id] ?? 0) === 0) {
                    $motivos[] = 'sin_usuarios';
                }

                $ultimaActividad = $ultimaActividadPorTenant[$tenant->id] ?? null;

                if ($ultimaActividad === null || Carbon::parse($ultimaActividad)->lt($limiteInactividad)) {
                    $motivos[] = 'sin_actividad';
                }

                if (empty($motivos)) {
                    return null;
                }

                return [
                    'id' => $tenant->id,
                    'nombre' => $tenant->nombre_comercial,
                    'motivos' => $motivos,
                    'gestion_url' => route('super_admin.tenants.index'),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }
}
