<?php

namespace App\Services;

use App\Enums\EstadoLead;
use App\Enums\EstadoPresupuesto;
use App\Enums\EtapaOportunidad;
use App\Models\Articulo;
use App\Models\Lead;
use App\Models\Oportunidad;
use App\Models\Presupuesto;
use App\Models\PresupuestoLinea;
use App\Support\AlcanceInformeComercial;
use App\Support\BucketsRango;
use App\Support\FiltrosInforme;
use App\Support\RangoFechas;
use App\Support\VariacionPorcentual;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

/**
 * Núcleo de cálculo del informe comercial (feature 033, data-model.md §4). No persiste nada: es
 * el resultado en memoria de aplicar `(periodo, filtros, alcance)` sobre leads/oportunidades/
 * presupuestos existentes, con agregación en base de datos (COUNT/SUM + GROUP BY, research D6).
 *
 * Los filtros de canal/comercial/fase ya llegan resueltos por `AlcanceInformeComercial::resolverFiltros()`
 * antes de entrar aquí: este servicio no distingue si `comercialId` vino de un filtro del gestor o
 * de la restricción forzada de un usuario sin `ver-informes-equipo`, aplica el mismo criterio en
 * ambos casos (un presupuesto solo tiene comercial a través de su oportunidad).
 */
class InformeComercial
{
    /**
     * Estados de presupuesto que cuentan como "aceptado" (D1): el estado `facturado` implica que
     * fue aceptado antes de convertirse.
     */
    private const ESTADOS_PRESUPUESTO_ACEPTADO = [
        EstadoPresupuesto::Aceptado,
        EstadoPresupuesto::Facturado,
    ];

    public function generar(RangoFechas $rango, FiltrosInforme $filtros, AlcanceInformeComercial $alcance): array
    {
        $indicadores = $this->indicadores($rango, $filtros, $alcance);
        $ratios = $this->ratios($rango, $filtros, $alcance, $indicadores);

        $resultado = [
            'periodo' => [
                'preset' => $rango->preset->value,
                'desde' => $rango->desde->toDateString(),
                'hasta' => $rango->hasta->toDateString(),
                'etiqueta' => $rango->etiqueta(),
            ],
            'alcance' => $alcance->aArray(),
            'filtros' => [
                'canal_id' => $filtros->canalId,
                'comercial_id' => $filtros->comercialId,
                'fase' => $filtros->fase,
                'comparar' => $filtros->comparar,
            ],
            'indicadores' => $indicadores,
            'ratios' => $ratios,
            'fases' => $this->fases($rango, $filtros, $alcance),
            'evolucion' => $this->evolucion($rango, $filtros, $alcance),
            'top_articulos' => $this->topArticulos($rango, $filtros, $alcance),
            'comparativa' => null,
        ];

        if ($filtros->comparar) {
            $rangoAnterior = $rango->mismoPeriodoEjercicioAnterior();
            $indicadoresAnterior = $this->indicadores($rangoAnterior, $filtros, $alcance);
            $ratiosAnterior = $this->ratios($rangoAnterior, $filtros, $alcance, $indicadoresAnterior);

            $resultado['comparativa'] = [
                'periodo' => [
                    'desde' => $rangoAnterior->desde->toDateString(),
                    'hasta' => $rangoAnterior->hasta->toDateString(),
                    'etiqueta' => $rangoAnterior->etiqueta(),
                ],
                'indicadores' => $indicadoresAnterior,
                'ratios' => $ratiosAnterior,
                'evolucion' => $this->evolucion($rangoAnterior, $filtros, $alcance),
                'variaciones' => [
                    'indicadores' => $this->variaciones($indicadores, $indicadoresAnterior),
                    'ratios' => $this->variaciones($ratios, $ratiosAnterior),
                ],
            ];
        }

        return $resultado;
    }

    // --- Queries base filtradas (canal/comercial/fase ya resueltos por el alcance) ---

    private function leadsQuery(FiltrosInforme $filtros): Builder
    {
        $query = Lead::query();

        if ($filtros->comercialId !== null) {
            $query->where('asignado_a', $filtros->comercialId);
        }

        if ($filtros->canalId !== null) {
            $filtros->canalId === 'sin_especificar'
                ? $query->whereNull('canal_captacion_id')
                : $query->where('canal_captacion_id', $filtros->canalId);
        }

        if ($filtros->fase !== null && EstadoLead::tryFrom($filtros->fase) !== null) {
            $query->where('estado', $filtros->fase);
        }

        return $query;
    }

    private function oportunidadesQuery(FiltrosInforme $filtros): Builder
    {
        $query = Oportunidad::query();

        if ($filtros->comercialId !== null) {
            $query->where('asignado_a', $filtros->comercialId);
        }

        if ($filtros->canalId !== null) {
            $query->whereHas('lead', function (Builder $lead) use ($filtros) {
                $filtros->canalId === 'sin_especificar'
                    ? $lead->whereNull('canal_captacion_id')
                    : $lead->where('canal_captacion_id', $filtros->canalId);
            });
        }

        if ($filtros->fase !== null && EtapaOportunidad::tryFrom($filtros->fase) !== null) {
            $query->where('etapa', $filtros->fase);
        }

        return $query;
    }

    /**
     * Un presupuesto sin oportunidad asociada queda fuera de cualquier filtro/alcance por
     * comercial (data-model.md §4): no hay forma de atribuirlo a nadie.
     */
    private function presupuestosQuery(FiltrosInforme $filtros): Builder
    {
        $query = Presupuesto::query();

        if ($filtros->comercialId !== null) {
            $query->whereHas('oportunidad', fn (Builder $o) => $o->where('asignado_a', $filtros->comercialId));
        }

        if ($filtros->canalId !== null) {
            $query->whereHas('lead', function (Builder $lead) use ($filtros) {
                $filtros->canalId === 'sin_especificar'
                    ? $lead->whereNull('canal_captacion_id')
                    : $lead->where('canal_captacion_id', $filtros->canalId);
            });
        }

        if ($filtros->fase !== null && EstadoPresupuesto::tryFrom($filtros->fase) !== null) {
            $query->where('estado', $filtros->fase);
        }

        return $query;
    }

    /**
     * Acota una columna de fecha a `[inicio, fin]` inclusive, con `< fin + 1 día` para no perder
     * registros por comparación de string cuando la columna guarda datetime (mismo patrón que
     * `DashboardEstadisticas::acotarFecha()`).
     */
    private function acotarFecha(Builder $query, string $columna, Carbon $inicio, Carbon $fin): Builder
    {
        return $query
            ->where($columna, '>=', $inicio->toDateString())
            ->where($columna, '<', $fin->copy()->addDay()->toDateString());
    }

    // --- Indicadores (FR-003, FR-030/031) ---

    private function indicadores(RangoFechas $rango, FiltrosInforme $filtros, AlcanceInformeComercial $alcance): array
    {
        $indicadores = [
            'leads_captados' => 0,
            'leads_convertidos' => 0,
            'oportunidades_creadas' => 0,
            'oportunidades_ganadas' => 0,
            'oportunidades_perdidas' => 0,
            'oportunidades_abiertas' => 0,
            'importe_pipeline' => 0.0,
            'presupuestos_emitidos' => 0,
            'importe_presupuestado' => 0.0,
            'presupuestos_aceptados' => 0,
            'importe_aceptado' => 0.0,
            'facturas_del_embudo' => 0,
            'importe_facturado_embudo' => 0.0,
        ];

        if ($alcance->tieneBloque('leads')) {
            $leadsCohorte = $this->acotarFecha($this->leadsQuery($filtros), 'created_at', $rango->desde, $rango->hasta);
            $indicadores['leads_captados'] = (clone $leadsCohorte)->count();
            $indicadores['leads_convertidos'] = (clone $leadsCohorte)->where('estado', EstadoLead::Convertido)->count();
        }

        if ($alcance->tieneBloque('oportunidades')) {
            $oportunidadesCohorte = $this->acotarFecha($this->oportunidadesQuery($filtros), 'created_at', $rango->desde, $rango->hasta);
            $indicadores['oportunidades_creadas'] = (clone $oportunidadesCohorte)->count();

            $oportunidadesEvento = $this->acotarFecha($this->oportunidadesQuery($filtros), 'cerrada_at', $rango->desde, $rango->hasta);
            $indicadores['oportunidades_ganadas'] = (clone $oportunidadesEvento)->where('etapa', EtapaOportunidad::Ganada)->count();
            $indicadores['oportunidades_perdidas'] = (clone $oportunidadesEvento)->where('etapa', EtapaOportunidad::Perdida)->count();

            $abiertas = $this->oportunidadesQuery($filtros)
                ->where('created_at', '<', $rango->hasta->copy()->addDay()->toDateString())
                ->where(function (Builder $q) use ($rango) {
                    $q->whereNull('cerrada_at')->orWhere('cerrada_at', '>', $rango->hasta->copy()->endOfDay());
                });
            $indicadores['oportunidades_abiertas'] = (clone $abiertas)->count();
            $indicadores['importe_pipeline'] = round((float) (clone $abiertas)->sum('importe_estimado'), 2);
        }

        if ($alcance->tieneBloque('presupuestos')) {
            $presupuestosCohorte = $this->acotarFecha($this->presupuestosQuery($filtros), 'fecha_emision', $rango->desde, $rango->hasta);

            $indicadores['presupuestos_emitidos'] = (clone $presupuestosCohorte)->count();
            $indicadores['importe_presupuestado'] = round((float) (clone $presupuestosCohorte)->sum('total'), 2);

            $aceptados = (clone $presupuestosCohorte)->whereIn('estado', array_map(
                fn (EstadoPresupuesto $e) => $e->value,
                self::ESTADOS_PRESUPUESTO_ACEPTADO,
            ));
            $indicadores['presupuestos_aceptados'] = (clone $aceptados)->count();
            $indicadores['importe_aceptado'] = round((float) (clone $aceptados)->sum('total'), 2);

            $embudo = (clone $presupuestosCohorte)->whereNotNull('convertido_a_factura_id');
            $indicadores['facturas_del_embudo'] = (clone $embudo)->count();
            $indicadores['importe_facturado_embudo'] = round((float) (clone $embudo)->sum('total'), 2);
        }

        return $indicadores;
    }

    // --- Ratios (FR-004/FR-005) ---

    private function ratios(RangoFechas $rango, FiltrosInforme $filtros, AlcanceInformeComercial $alcance, array $indicadores): array
    {
        $ratios = [
            'conversion_lead_cliente' => null,
            'oportunidades_ganadas' => null,
            'aceptacion_presupuestos' => null,
            'conversion_presupuesto_factura' => null,
            'importe_medio_ganada' => null,
            'ciclo_medio_lead_dias' => null,
            'ciclo_medio_oportunidad_dias' => null,
        ];

        if ($alcance->tieneBloque('leads')) {
            $ratios['conversion_lead_cliente'] = $this->ratioPct($indicadores['leads_convertidos'], $indicadores['leads_captados']);

            $leadsConvertidos = $this->acotarFecha($this->leadsQuery($filtros), 'created_at', $rango->desde, $rango->hasta)
                ->where('estado', EstadoLead::Convertido)
                ->whereNotNull('convertido_at')
                ->get(['created_at', 'convertido_at']);
            $ratios['ciclo_medio_lead_dias'] = $this->promedioDiasEntre($leadsConvertidos, 'created_at', 'convertido_at');
        }

        if ($alcance->tieneBloque('oportunidades')) {
            $cerradas = $indicadores['oportunidades_ganadas'] + $indicadores['oportunidades_perdidas'];
            $ratios['oportunidades_ganadas'] = $this->ratioPct($indicadores['oportunidades_ganadas'], $cerradas);

            $ganadasEvento = $this->acotarFecha($this->oportunidadesQuery($filtros), 'cerrada_at', $rango->desde, $rango->hasta)
                ->where('etapa', EtapaOportunidad::Ganada);
            $importeMedio = (clone $ganadasEvento)->avg('importe_estimado');
            $ratios['importe_medio_ganada'] = $importeMedio !== null ? round((float) $importeMedio, 2) : null;

            $cerradasEvento = $this->acotarFecha($this->oportunidadesQuery($filtros), 'cerrada_at', $rango->desde, $rango->hasta)
                ->whereIn('etapa', [EtapaOportunidad::Ganada->value, EtapaOportunidad::Perdida->value])
                ->get(['created_at', 'cerrada_at']);
            $ratios['ciclo_medio_oportunidad_dias'] = $this->promedioDiasEntre($cerradasEvento, 'created_at', 'cerrada_at');
        }

        if ($alcance->tieneBloque('presupuestos')) {
            $ratios['aceptacion_presupuestos'] = $this->ratioPct($indicadores['presupuestos_aceptados'], $indicadores['presupuestos_emitidos']);
            $ratios['conversion_presupuesto_factura'] = $this->ratioPct($indicadores['facturas_del_embudo'], $indicadores['presupuestos_emitidos']);
        }

        return $ratios;
    }

    private function ratioPct(int $numerador, int $denominador): ?float
    {
        if ($denominador === 0) {
            return null;
        }

        return round(($numerador / $denominador) * 100, 2);
    }

    /**
     * Promedio en días entre dos columnas datetime de una colección ya cargada. Se calcula en PHP
     * (Carbon) en vez de `DATEDIFF` en SQL para no depender de una función específica de MySQL
     * (los tests corren sobre SQLite, research D6/SC-007: el volumen de esta subconsulta ya está
     * acotado por el propio periodo, igual que los indicadores de los que deriva).
     *
     * @param  \Illuminate\Support\Collection<int, object>  $filas
     */
    private function promedioDiasEntre($filas, string $columnaInicio, string $columnaFin): ?float
    {
        if ($filas->isEmpty()) {
            return null;
        }

        $total = $filas->sum(fn ($fila) => Carbon::parse($fila->{$columnaInicio})->diffInDays(Carbon::parse($fila->{$columnaFin})));

        return round($total / $filas->count(), 2);
    }

    // --- Desglose por fases (FR-007) ---

    private function fases(RangoFechas $rango, FiltrosInforme $filtros, AlcanceInformeComercial $alcance): array
    {
        return [
            'leads_por_estado' => $alcance->tieneBloque('leads') ? $this->leadsPorEstado($rango, $filtros) : [],
            'oportunidades_por_etapa' => $alcance->tieneBloque('oportunidades') ? $this->oportunidadesPorEtapa($rango, $filtros) : [],
            'presupuestos_por_estado' => $alcance->tieneBloque('presupuestos') ? $this->presupuestosPorEstado($rango, $filtros) : [],
        ];
    }

    private function leadsPorEstado(RangoFechas $rango, FiltrosInforme $filtros): array
    {
        $conteos = $this->acotarFecha($this->leadsQuery($filtros), 'created_at', $rango->desde, $rango->hasta)
            ->selectRaw('estado, COUNT(*) as cantidad')
            ->groupBy('estado')
            ->pluck('cantidad', 'estado');

        return collect(EstadoLead::cases())
            ->map(fn (EstadoLead $estado) => [
                'estado' => $estado->value,
                'etiqueta' => $estado->label(),
                'cantidad' => (int) ($conteos[$estado->value] ?? 0),
            ])
            ->all();
    }

    private function oportunidadesPorEtapa(RangoFechas $rango, FiltrosInforme $filtros): array
    {
        $filas = $this->acotarFecha($this->oportunidadesQuery($filtros), 'created_at', $rango->desde, $rango->hasta)
            ->selectRaw('etapa, COUNT(*) as cantidad, SUM(importe_estimado) as importe')
            ->groupBy('etapa')
            ->get()
            ->keyBy('etapa');

        return collect(EtapaOportunidad::cases())
            ->map(fn (EtapaOportunidad $etapa) => [
                'etapa' => $etapa->value,
                'etiqueta' => $etapa->label(),
                'cantidad' => (int) ($filas[$etapa->value]->cantidad ?? 0),
                'importe' => round((float) ($filas[$etapa->value]->importe ?? 0), 2),
            ])
            ->all();
    }

    private function presupuestosPorEstado(RangoFechas $rango, FiltrosInforme $filtros): array
    {
        $filas = $this->acotarFecha($this->presupuestosQuery($filtros), 'fecha_emision', $rango->desde, $rango->hasta)
            ->selectRaw('estado, COUNT(*) as cantidad, SUM(total) as importe')
            ->groupBy('estado')
            ->get()
            ->keyBy('estado');

        return collect(EstadoPresupuesto::cases())
            ->map(fn (EstadoPresupuesto $estado) => [
                'estado' => $estado->value,
                'etiqueta' => $estado->label(),
                'cantidad' => (int) ($filas[$estado->value]->cantidad ?? 0),
                'importe' => round((float) ($filas[$estado->value]->importe ?? 0), 2),
            ])
            ->all();
    }

    // --- Evolución temporal (FR-008) ---

    private function evolucion(RangoFechas $rango, FiltrosInforme $filtros, AlcanceInformeComercial $alcance): array
    {
        return collect(BucketsRango::bucketsDelRango($rango))
            ->map(function (array $bucket) use ($filtros, $alcance) {
                return [
                    'etiqueta' => $bucket['etiqueta'],
                    'leads' => $alcance->tieneBloque('leads')
                        ? $this->acotarFecha($this->leadsQuery($filtros), 'created_at', $bucket['inicio'], $bucket['fin'])->count()
                        : 0,
                    'oportunidades' => $alcance->tieneBloque('oportunidades')
                        ? $this->acotarFecha($this->oportunidadesQuery($filtros), 'created_at', $bucket['inicio'], $bucket['fin'])->count()
                        : 0,
                    'presupuestos' => $alcance->tieneBloque('presupuestos')
                        ? $this->acotarFecha($this->presupuestosQuery($filtros), 'fecha_emision', $bucket['inicio'], $bucket['fin'])->count()
                        : 0,
                ];
            })
            ->all();
    }

    // --- Top artículos más presupuestados (FR-009) ---

    private function topArticulos(RangoFechas $rango, FiltrosInforme $filtros, AlcanceInformeComercial $alcance): array
    {
        if (! $alcance->tieneBloque('presupuestos')) {
            return [];
        }

        $presupuestoIds = $this->acotarFecha($this->presupuestosQuery($filtros), 'fecha_emision', $rango->desde, $rango->hasta)
            ->pluck('id');

        if ($presupuestoIds->isEmpty()) {
            return [];
        }

        $filas = PresupuestoLinea::query()
            ->whereIn('presupuesto_id', $presupuestoIds)
            ->whereNotNull('articulo_id')
            ->selectRaw('articulo_id, SUM(base) as importe, SUM(cantidad) as unidades')
            ->groupBy('articulo_id')
            ->orderByDesc('importe')
            ->limit(5)
            ->get();

        $nombres = Articulo::query()->whereIn('id', $filas->pluck('articulo_id'))->pluck('nombre', 'id');

        return $filas->map(fn ($fila) => [
            'articulo_id' => (int) $fila->articulo_id,
            'nombre' => $nombres[$fila->articulo_id] ?? '—',
            'importe' => round((float) $fila->importe, 2),
            'unidades' => round((float) $fila->unidades, 4),
        ])->all();
    }

    // --- Comparativa entre ejercicios (FR-018/FR-020) ---

    private function variaciones(array $actual, array $anterior): array
    {
        $variaciones = [];

        foreach ($actual as $clave => $valor) {
            $valorAnterior = $anterior[$clave] ?? null;

            $variaciones[$clave] = ($valor === null || $valorAnterior === null)
                ? null
                : VariacionPorcentual::calcular((float) $valor, (float) $valorAnterior);
        }

        return $variaciones;
    }
}
