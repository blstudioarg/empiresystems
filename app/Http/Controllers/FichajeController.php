<?php

namespace App\Http\Controllers;

use App\Enums\TipoEventoFichaje;
use App\Exceptions\FichajeBloqueadoException;
use App\Http\Requests\FicharRequest;
use App\Models\Fichaje;
use App\Models\MiembroEquipo;
use App\Services\InformeJornada;
use App\Services\RegistroFichajes;
use App\Support\ConfigFichajes;
use App\Support\ConfigTenant;
use App\Support\ResolutorHorario;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class FichajeController extends Controller
{
    public function __construct(
        private readonly RegistroFichajes $registroFichajes,
        private readonly InformeJornada $informeJornada,
    ) {}

    public function index(): View
    {
        $miembro = auth()->user()->miembroEquipo;
        $ahoraLocal = Carbon::now($miembro ? ConfigTenant::zonaHoraria($miembro->tenant_id) : ConfigTenant::DEFAULT_ZONA_HORARIA);

        return view('fichajes.index', [
            'miembro' => $miembro,
            'estado' => $miembro ? $this->estadoActual($miembro->id) : 'cerrada',
            'registrarPausas' => $miembro ? ConfigFichajes::registrarPausas($miembro->tenant_id) : false,
            'resumenHoy' => $miembro ? $this->resumenHoy($miembro, $ahoraLocal) : null,
            'turnoHoy' => $miembro ? $this->turnoHoy($miembro, $ahoraLocal) : null,
            'eventosHoy' => $miembro ? $this->eventosHoy($miembro, $ahoraLocal) : collect(),
            'ahoraLocal' => $ahoraLocal,
        ]);
    }

    public function store(FicharRequest $request): RedirectResponse|JsonResponse
    {
        $miembro = $request->user()->miembroEquipo;

        try {
            $fichaje = $this->registroFichajes->registrar(
                $miembro,
                TipoEventoFichaje::from($request->string('tipo')->toString()),
                $request->filled('latitud') ? (float) $request->input('latitud') : null,
                $request->filled('longitud') ? (float) $request->input('longitud') : null,
                $request->filled('precision') ? (int) $request->input('precision') : null,
                $request->ip(),
                $request->userAgent(),
            );
        } catch (FichajeBloqueadoException $e) {
            if ($request->wantsJson()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return redirect()->route('fichajes.index')->with('error', $e->getMessage())->setStatusCode(422);
        }

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Fichaje registrado correctamente.',
                'tipo' => $fichaje->tipo->value,
                'tipo_label' => $fichaje->tipo->label(),
                'hora' => ConfigTenant::paraMostrar($fichaje->ocurrido_at, $miembro->tenant_id)->format('H:i'),
                'resultado_ubicacion' => $fichaje->resultado_ubicacion->value,
                'resultado_ubicacion_label' => $fichaje->resultado_ubicacion->label(),
                'estado' => $this->estadoActual($miembro->id),
            ]);
        }

        return redirect()->route('fichajes.index')->with('success', 'Fichaje registrado correctamente.');
    }

    private function estadoActual(int $miembroId): string
    {
        $ultimo = Fichaje::where('miembro_equipo_id', $miembroId)
            ->whereNull('corrige_fichaje_id')
            ->orderByDesc('ocurrido_at')
            ->orderByDesc('id')
            ->first();

        return match ($ultimo?->tipo) {
            null, TipoEventoFichaje::Salida => 'cerrada',
            TipoEventoFichaje::Entrada, TipoEventoFichaje::FinPausa => 'abierta',
            TipoEventoFichaje::InicioPausa => 'en_pausa',
        };
    }

    /**
     * Horas trabajadas hoy para mostrar en vivo: segundos ya consolidados (tramos cerrados de
     * hoy) +, si la jornada está abierta ahora mismo, el instante desde el que el cliente debe
     * seguir sumando en vivo (JS hace el tick, el servidor nunca deja de ser la fuente de verdad
     * de lo que finalmente se persiste al fichar salida).
     *
     * @return array{segundos_base: int, contando_desde: ?string, en_pausa: bool}
     */
    private function resumenHoy(MiembroEquipo $miembro, Carbon $ahoraLocal): array
    {
        $inicioHoy = $ahoraLocal->copy()->startOfDay()->setTimezone(config('app.timezone'));
        $finHoy = $ahoraLocal->copy()->endOfDay()->setTimezone(config('app.timezone'));
        $eventos = $this->informeJornada->eventosEfectivos($miembro, $inicioHoy, $finHoy);

        $segundos = 0;
        $trabajandoDesde = null;
        $pausaDesde = null;

        foreach ($eventos as $evento) {
            if ($evento->tipo === TipoEventoFichaje::Entrada || $evento->tipo === TipoEventoFichaje::FinPausa) {
                $trabajandoDesde = $evento->ocurrido_at;
                $pausaDesde = null;
            } elseif ($evento->tipo === TipoEventoFichaje::InicioPausa) {
                if ($trabajandoDesde !== null) {
                    $segundos += $evento->ocurrido_at->getTimestamp() - $trabajandoDesde->getTimestamp();
                }
                $trabajandoDesde = null;
                $pausaDesde = $evento->ocurrido_at;
            } elseif ($evento->tipo === TipoEventoFichaje::Salida) {
                if ($trabajandoDesde !== null) {
                    $segundos += $evento->ocurrido_at->getTimestamp() - $trabajandoDesde->getTimestamp();
                }
                $trabajandoDesde = null;
                $pausaDesde = null;
            }
        }

        return [
            'segundos_base' => max(0, $segundos),
            'contando_desde' => $trabajandoDesde?->toIso8601String(),
            'en_pausa' => $pausaDesde !== null,
        ];
    }

    /**
     * @return array<int, array{hora_inicio: string, hora_fin: string}>|null null = sin horario vigente hoy
     */
    private function turnoHoy(MiembroEquipo $miembro, Carbon $ahoraLocal): ?array
    {
        $horario = ResolutorHorario::vigente($miembro, $ahoraLocal);

        if ($horario === null) {
            return null;
        }

        return $horario->tramos
            ->where('dia_semana', $ahoraLocal->dayOfWeekIso)
            ->sortBy('hora_inicio')
            ->map(fn ($tramo) => [
                'hora_inicio' => substr($tramo->hora_inicio, 0, 5),
                'hora_fin' => substr($tramo->hora_fin, 0, 5),
            ])
            ->values()
            ->all();
    }

    /**
     * Últimos fichajes de hoy (zona local del tenant) con `ocurrido_at` ya convertido para mostrar
     * — estas instancias son de solo lectura para la vista, nunca se vuelven a guardar.
     */
    private function eventosHoy(MiembroEquipo $miembro, Carbon $ahoraLocal)
    {
        $inicioHoy = $ahoraLocal->copy()->startOfDay()->setTimezone(config('app.timezone'));
        $finHoy = $ahoraLocal->copy()->endOfDay()->setTimezone(config('app.timezone'));

        return Fichaje::where('miembro_equipo_id', $miembro->id)
            ->whereNull('corrige_fichaje_id')
            ->whereBetween('ocurrido_at', [$inicioHoy, $finHoy])
            ->orderByDesc('ocurrido_at')
            ->limit(6)
            ->get()
            ->each(function (Fichaje $fichaje) use ($miembro) {
                $fichaje->ocurrido_at = ConfigTenant::paraMostrar($fichaje->ocurrido_at, $miembro->tenant_id);
            });
    }
}
