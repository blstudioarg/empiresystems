<?php

namespace App\Http\Controllers;

use App\Enums\VeredictoCumplimiento;
use App\Models\Fichaje;
use App\Models\Horario;
use App\Models\MiembroEquipo;
use App\Support\ConfigTenant;
use App\Support\Cumplimiento\ServicioCumplimiento;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Calendario de fichajes y horarios (feature 026): proyección de solo lectura sobre los datos
 * de 024/025. `eventos()` es el feed JSON que consume FullCalendar por rango visible; todo el
 * cálculo (veredictos, tramos previstos, intervalos reales) ocurre en backend (Principio III).
 * Sin tablas nuevas ni escrituras: las acciones reutilizan los endpoints de 024/025.
 */
class CalendarioController extends Controller
{
    /** Tope de días por request del feed: protege el cálculo al vuelo (research D2). */
    private const MAX_DIAS_RANGO = 62;

    public function __construct(private readonly ServicioCumplimiento $cumplimiento) {}

    public function index(): View
    {
        $miembros = MiembroEquipo::where('activo', true)->with('user')->get()
            ->sortBy(fn (MiembroEquipo $m) => $m->user->name)->values();

        return view('calendario.index', [
            'miembros' => $miembros,
            'horarios' => Horario::orderBy('nombre')->get(),
        ]);
    }

    public function eventos(Request $request): JsonResponse
    {
        $request->validate([
            'start' => ['required', 'date'],
            'end' => ['required', 'date', 'after:start'],
            'miembro_equipo_id' => ['nullable', 'integer'],
        ]);

        // FullCalendar manda `end` exclusivo: el rango de días evaluado es [start, end).
        $desde = Carbon::parse($request->query('start'))->startOfDay();
        $fin = Carbon::parse($request->query('end'))->startOfDay();

        if ($desde->diffInDays($fin) > self::MAX_DIAS_RANGO) {
            abort(422, 'El rango solicitado supera el máximo de '.self::MAX_DIAS_RANGO.' días.');
        }

        $tenantId = tenant()->getTenantKey();
        // Regla D4: nunca hay veredicto para hoy ni fechas futuras. "Hoy" en la zona horaria del
        // tenant (los días del calendario son días locales del tenant, igual que sus vistas).
        $hoy = Carbon::now(ConfigTenant::zonaHoraria($tenantId))->toDateString();

        if ($request->filled('miembro_equipo_id')) {
            // Resolución manual dentro del tenant (memoria project_tenant_route_binding).
            $miembro = MiembroEquipo::where('tenant_id', $tenantId)
                ->findOrFail($request->integer('miembro_equipo_id'));

            return response()->json($this->eventosMiembro($miembro, $desde, $fin, $hoy));
        }

        return response()->json($this->eventosEquipo($tenantId, $desde, $fin, $hoy));
    }

    /**
     * Métricas agregadas del rango visible (feature 026, panel superior): KPIs, distribución de
     * veredictos y horas previstas/trabajadas por semana. Solo días pasados (D4), mismo cálculo
     * al vuelo que el feed (Principio III: nada se agrega en el cliente). Modo miembro = 1 miembro;
     * modo equipo = todos los activos.
     */
    public function resumen(Request $request): JsonResponse
    {
        $request->validate([
            'start' => ['required', 'date'],
            'end' => ['required', 'date', 'after:start'],
            'miembro_equipo_id' => ['nullable', 'integer'],
        ]);

        $desde = Carbon::parse($request->query('start'))->startOfDay();
        $fin = Carbon::parse($request->query('end'))->startOfDay();

        if ($desde->diffInDays($fin) > self::MAX_DIAS_RANGO) {
            abort(422, 'El rango solicitado supera el máximo de '.self::MAX_DIAS_RANGO.' días.');
        }

        $tenantId = tenant()->getTenantKey();
        $hoy = Carbon::now(ConfigTenant::zonaHoraria($tenantId))->toDateString();

        if ($request->filled('miembro_equipo_id')) {
            $miembro = MiembroEquipo::where('tenant_id', $tenantId)
                ->findOrFail($request->integer('miembro_equipo_id'));

            return response()->json($this->agregarResumen(collect([$miembro]), $desde, $fin, $hoy, 'miembro'));
        }

        $miembros = MiembroEquipo::where('tenant_id', $tenantId)->where('activo', true)->get();

        return response()->json($this->agregarResumen($miembros, $desde, $fin, $hoy, 'equipo'));
    }

    /**
     * Recorre (miembros × días pasados) evaluando cada día una sola vez y acumula los agregados
     * que consume el panel de métricas. Mismos veredictos y colores que el calendario (D6).
     *
     * @param  \Illuminate\Support\Collection<int, MiembroEquipo>  $miembros
     * @return array<string, mixed>
     */
    private function agregarResumen(Collection $miembros, Carbon $desde, Carbon $fin, string $hoy, string $modo): array
    {
        $diasLaborables = 0;
        $diasCumplidos = 0;
        $diasRetraso = 0;
        $minutosRetraso = 0;
        $ausencias = 0;
        $incidencias = 0;
        $horasPrevistas = 0.0;
        $horasTrabajadas = 0.0;

        /** @var array<string, int> $distribucion veredicto => nº de días-miembro */
        $distribucion = [];
        /** @var array<string, array{previstas: float, trabajadas: float}> $porDia */
        $porDia = [];
        /** @var array<string, array{etiqueta: string, previstas: float, trabajadas: float}> $porSemana */
        $porSemana = [];

        for ($dia = $desde->copy(); $dia->lt($fin); $dia->addDay()) {
            $fecha = $dia->toDateString();

            if ($fecha >= $hoy) {
                continue;
            }

            $porDia[$fecha] = ['previstas' => 0.0, 'trabajadas' => 0.0];
            $semanaKey = $dia->format('o-\WW');
            $porSemana[$semanaKey] ??= ['etiqueta' => $dia->copy()->startOfWeek()->format('d/m'), 'previstas' => 0.0, 'trabajadas' => 0.0];

            foreach ($miembros as $miembro) {
                $resultado = $this->cumplimiento->evaluarDia($miembro, $dia);

                $distribucion[$resultado->veredicto->value] = ($distribucion[$resultado->veredicto->value] ?? 0) + 1;
                $horasPrevistas += $resultado->horasPrevistas;
                $horasTrabajadas += $resultado->horasTrabajadas;
                $porDia[$fecha]['previstas'] += $resultado->horasPrevistas;
                $porDia[$fecha]['trabajadas'] += $resultado->horasTrabajadas;
                $porSemana[$semanaKey]['previstas'] += $resultado->horasPrevistas;
                $porSemana[$semanaKey]['trabajadas'] += $resultado->horasTrabajadas;

                if ($resultado->veredicto !== VeredictoCumplimiento::Libre) {
                    $diasLaborables++;
                }
                $diasCumplidos += (int) ($resultado->veredicto === VeredictoCumplimiento::Cumplido);
                $diasRetraso += (int) ($resultado->veredicto === VeredictoCumplimiento::Retraso);
                $minutosRetraso += $resultado->minutosRetraso;
                $ausencias += (int) ($resultado->veredicto === VeredictoCumplimiento::Ausencia);
                $incidencias += (int) $resultado->incidencia;
            }
        }

        return [
            'modo' => $modo,
            'kpis' => [
                'cumplimiento_pct' => $diasLaborables > 0 ? round($diasCumplidos / $diasLaborables * 100) : null,
                'dias_laborables' => $diasLaborables,
                'dias_cumplidos' => $diasCumplidos,
                'horas_previstas' => round($horasPrevistas, 1),
                'horas_trabajadas' => round($horasTrabajadas, 1),
                'diferencia_horas' => round($horasTrabajadas - $horasPrevistas, 1),
                'dias_retraso' => $diasRetraso,
                'minutos_retraso' => $minutosRetraso,
                'ausencias' => $ausencias,
                'incidencias' => $incidencias,
            ],
            // Tendencia diaria de % de horas cubiertas (trabajadas/previstas) para el sparkline.
            'sparkline' => collect($porDia)
                ->filter(fn (array $d) => $d['previstas'] > 0)
                ->map(fn (array $d) => (int) round($d['trabajadas'] / $d['previstas'] * 100))
                ->values()
                ->all(),
            // Solo veredictos presentes; el front les pone el color exacto del calendario por clave.
            'distribucion' => collect(VeredictoCumplimiento::cases())
                ->filter(fn (VeredictoCumplimiento $v) => ($distribucion[$v->value] ?? 0) > 0)
                ->map(fn (VeredictoCumplimiento $v) => [
                    'veredicto' => $v->value,
                    'label' => $v->label(),
                    'cantidad' => $distribucion[$v->value],
                ])
                ->values()
                ->all(),
            'semanas' => collect($porSemana)
                ->sortKeys()
                ->map(fn (array $s) => [
                    'etiqueta' => $s['etiqueta'],
                    'previstas' => round($s['previstas'], 1),
                    'trabajadas' => round($s['trabajadas'], 1),
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * Modo miembro: `veredicto_dia` (días pasados) + `previsto` (todos los días con horario,
     * futuro incluido) + `real` (intervalos trabajados, correcciones aplicadas).
     *
     * @return array<int, array<string, mixed>>
     */
    private function eventosMiembro(MiembroEquipo $miembro, Carbon $desde, Carbon $fin, string $hoy): array
    {
        $eventos = [];
        $fichajesRango = $this->fichajesPorDia($miembro, $desde, $fin);

        for ($dia = $desde->copy(); $dia->lt($fin); $dia->addDay()) {
            $fecha = $dia->toDateString();

            if ($fecha < $hoy) {
                $resultado = $this->cumplimiento->evaluarDia($miembro, $dia);
                $eventos[] = [
                    'start' => $fecha,
                    'allDay' => true,
                    'display' => 'background',
                    'classNames' => [$resultado->veredicto->clase()],
                    'extendedProps' => [
                        'tipo' => 'veredicto_dia',
                        'veredicto' => $resultado->veredicto->value,
                        'veredicto_label' => $resultado->veredicto->label(),
                        'horas_previstas' => $resultado->horasPrevistas,
                        'horas_trabajadas' => $resultado->horasTrabajadas,
                        'minutos_retraso' => $resultado->minutosRetraso,
                        'diferencia_horas' => $resultado->diferenciaHoras,
                        'incidencia' => $resultado->incidencia,
                        'fichajes' => $fichajesRango[$fecha] ?? [],
                    ],
                ];
            }

            foreach ($this->tramosPrevistos($miembro, $dia) as $tramo) {
                $eventos[] = $tramo;
            }

            foreach ($this->intervalosReales($miembro, $dia, $fichajesRango[$fecha] ?? []) as $real) {
                $eventos[] = $real;
            }
        }

        return $eventos;
    }

    /**
     * Tramos del horario vigente del día, anclados a la fecha, como eventos `previsto`.
     *
     * @return array<int, array<string, mixed>>
     */
    private function tramosPrevistos(MiembroEquipo $miembro, Carbon $dia): array
    {
        $horario = $this->cumplimiento->resolverHorario($miembro, $dia);

        if ($horario === null) {
            return [];
        }

        return $horario->tramos
            ->where('dia_semana', $dia->dayOfWeekIso)
            ->sortBy('hora_inicio')
            ->map(fn ($tramo) => [
                'start' => $dia->toDateString().'T'.substr($tramo->hora_inicio, 0, 5).':00',
                'end' => $dia->toDateString().'T'.substr($tramo->hora_fin, 0, 5).':00',
                'display' => 'background',
                'classNames' => ['cal-previsto'],
                'extendedProps' => ['tipo' => 'previsto', 'horario' => $horario->nombre],
            ])
            ->values()
            ->all();
    }

    /**
     * Intervalos realmente trabajados del día como eventos `real` (vistas timeGrid), en la zona
     * horaria del tenant.
     *
     * @param  array<int, array<string, mixed>>  $fichajesDia
     * @return array<int, array<string, mixed>>
     */
    private function intervalosReales(MiembroEquipo $miembro, Carbon $dia, array $fichajesDia): array
    {
        return array_map(fn (array $intervalo) => [
            'start' => $intervalo[0]->enZonaTenant()->format('Y-m-d\TH:i:s'),
            'end' => $intervalo[1]->enZonaTenant()->format('Y-m-d\TH:i:s'),
            'classNames' => ['cal-real'],
            'extendedProps' => ['tipo' => 'real', 'fichajes' => $fichajesDia],
        ], $this->cumplimiento->intervalosDia($miembro, $dia));
    }

    /**
     * Detalle de fichajes del rango agrupado por fecha local del tenant, para el modal de
     * detalle de día (US4) sin endpoint extra. Incluye correcciones señaladas.
     *
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function fichajesPorDia(MiembroEquipo $miembro, Carbon $desde, Carbon $fin): array
    {
        return Fichaje::where('miembro_equipo_id', $miembro->id)
            ->whereBetween('ocurrido_at', [$desde->copy()->startOfDay(), $fin->copy()->endOfDay()])
            ->orderBy('ocurrido_at')
            ->orderBy('id')
            ->get()
            ->groupBy(fn (Fichaje $f) => $f->ocurrido_at->enZonaTenant()->toDateString())
            ->map(fn ($fichajes) => $fichajes->map(fn (Fichaje $f) => [
                'id' => $f->id,
                'tipo' => $f->tipo->value,
                'tipo_label' => $f->tipo->label(),
                'hora' => $f->ocurrido_at->enZonaTenant()->format('H:i'),
                'ocurrido_at' => $f->ocurrido_at->enZonaTenant()->format('Y-m-d\TH:i'),
                'resultado_ubicacion' => $f->resultado_ubicacion?->label(),
                'es_correccion' => $f->corrige_fichaje_id !== null,
                'corrige_fichaje_id' => $f->corrige_fichaje_id,
                'motivo' => $f->motivo,
                'corregir_url' => $f->corrige_fichaje_id === null ? route('fichajes.corregir', $f) : null,
            ])->values()->all())
            ->all();
    }

    /**
     * Modo equipo (US3): un `resumen_equipo` por día pasado con ≥1 incumplimiento/incidencia.
     *
     * @return array<int, array<string, mixed>>
     */
    private function eventosEquipo(int $tenantId, Carbon $desde, Carbon $fin, string $hoy): array
    {
        // D3: miembros activos × días pasados con evaluarDia al vuelo (sin caché ni precálculo,
        // FR-019a de 025). Escala pyme: ≤50 miembros × ≤62 días por request.
        $miembros = MiembroEquipo::where('tenant_id', $tenantId)
            ->where('activo', true)
            ->with('user')
            ->get();

        $eventos = [];

        for ($dia = $desde->copy(); $dia->lt($fin); $dia->addDay()) {
            if ($dia->toDateString() >= $hoy) {
                continue;
            }

            $ausencias = 0;
            $retrasos = 0;
            $incidencias = 0;
            $afectados = [];

            foreach ($miembros as $miembro) {
                $resultado = $this->cumplimiento->evaluarDia($miembro, $dia);
                $incumple = $resultado->veredicto !== VeredictoCumplimiento::Cumplido
                    && $resultado->veredicto !== VeredictoCumplimiento::Libre;

                if (! $incumple && ! $resultado->incidencia) {
                    continue;
                }

                $ausencias += (int) ($resultado->veredicto === VeredictoCumplimiento::Ausencia);
                $retrasos += (int) ($resultado->veredicto === VeredictoCumplimiento::Retraso);
                $incidencias += (int) $resultado->incidencia;
                $afectados[] = [
                    'id' => $miembro->id,
                    'nombre' => $miembro->user->name,
                    'veredicto' => $resultado->veredicto->value,
                    'veredicto_label' => $resultado->veredicto->label(),
                ];
            }

            if ($afectados === []) {
                continue;
            }

            $eventos[] = [
                'start' => $dia->toDateString(),
                'allDay' => true,
                'classNames' => ['cal-resumen-equipo'],
                'extendedProps' => [
                    'tipo' => 'resumen_equipo',
                    'ausencias' => $ausencias,
                    'retrasos' => $retrasos,
                    'incidencias' => $incidencias,
                    'miembros' => $afectados,
                ],
            ];
        }

        return $eventos;
    }
}
