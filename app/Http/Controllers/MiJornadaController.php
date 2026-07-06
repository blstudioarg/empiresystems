<?php

namespace App\Http\Controllers;

use App\Services\InformeJornada;
use App\Support\RangoFechas;
use App\Support\ResolutorHorario;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class MiJornadaController extends Controller
{
    public function __construct(private readonly InformeJornada $informeJornada) {}

    public function index(Request $request): View|JsonResponse
    {
        $miembro = $request->user()->miembroEquipo;

        abort_if($miembro === null, 403, 'Tu cuenta no tiene un perfil de miembro de equipo asociado.');

        $rango = RangoFechas::desdePeticion($request->all());
        $datos = $this->informeJornada->generar($miembro, $rango->desde, $rango->hasta->copy()->endOfDay());

        if ($request->wantsJson()) {
            return response()->json([
                'html' => view('partials.mi-jornada-resultado', [
                    'rango' => $rango,
                    'datos' => $datos,
                ])->render(),
            ]);
        }

        return view('mi-jornada.index', [
            'miembro' => $miembro,
            'rango' => $rango,
            'datos' => $datos,
            'turnoHoy' => $this->turnoDia($miembro, now()),
            'turnoSemana' => $this->turnoSemana($miembro),
        ]);
    }

    /**
     * @return array<int, array{hora_inicio: string, hora_fin: string}>|null null = sin horario vigente hoy
     */
    private function turnoDia(\App\Models\MiembroEquipo $miembro, Carbon $fecha): ?array
    {
        $horario = ResolutorHorario::vigente($miembro, $fecha);

        if ($horario === null) {
            return null;
        }

        return $horario->tramos
            ->where('dia_semana', $fecha->dayOfWeekIso)
            ->sortBy('hora_inicio')
            ->map(fn ($tramo) => [
                'hora_inicio' => substr($tramo->hora_inicio, 0, 5),
                'hora_fin' => substr($tramo->hora_fin, 0, 5),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{dia_semana: int, tramos: array<int, array{hora_inicio: string, hora_fin: string}>}>|null
     */
    private function turnoSemana(\App\Models\MiembroEquipo $miembro): ?array
    {
        $horario = ResolutorHorario::vigente($miembro, now());

        if ($horario === null) {
            return null;
        }

        return collect(range(1, 7))->map(fn (int $dia) => [
            'dia_semana' => $dia,
            'tramos' => $horario->tramos
                ->where('dia_semana', $dia)
                ->sortBy('hora_inicio')
                ->map(fn ($tramo) => [
                    'hora_inicio' => substr($tramo->hora_inicio, 0, 5),
                    'hora_fin' => substr($tramo->hora_fin, 0, 5),
                ])
                ->values()
                ->all(),
        ])->all();
    }

    public function exportar(Request $request): Response
    {
        $miembro = $request->user()->miembroEquipo;

        abort_if($miembro === null, 403, 'Tu cuenta no tiene un perfil de miembro de equipo asociado.');

        $rango = RangoFechas::desdePeticion($request->all());
        $datos = $this->informeJornada->generar($miembro, $rango->desde, $rango->hasta->copy()->endOfDay());

        $pdf = Pdf::loadView('fichajes.informe-pdf', [
            'miembro' => $miembro,
            'rango' => $rango,
            'datos' => $datos,
        ]);

        return $pdf->stream('mi-jornada.pdf');
    }
}
