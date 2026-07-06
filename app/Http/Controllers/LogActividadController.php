<?php

namespace App\Http\Controllers;

use App\Enums\AccionLogActividad;
use App\Enums\ResultadoLogActividad;
use App\Models\LogActividad;
use App\Support\AgenteUsuario;
use App\Support\GeolocalizadorIp;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LogActividadController extends Controller
{
    /** @var array<string, string> */
    private const COLUMNAS_ORDENABLES = [
        'fecha' => 'ocurrido_at',
        'usuario_nombre' => 'usuario_nombre',
        'accion' => 'accion',
        'resultado' => 'resultado',
    ];

    public function index(Request $request): View|JsonResponse
    {
        if (! $request->has('draw')) {
            return view('logs.index');
        }

        // Filtro explícito por tenant además del global scope de BelongsToTenant (Principio I,
        // memoria project_tenant_route_binding): se aplica antes de search/order/paginación.
        $query = LogActividad::where('tenant_id', auth()->user()->tenant_id);

        $recordsTotal = (clone $query)->count();

        $termino = trim((string) $request->input('search.value', ''));

        if ($termino !== '') {
            $accionesCoincidentes = collect(AccionLogActividad::cases())
                ->filter(fn (AccionLogActividad $accion) => str_contains(mb_strtolower($accion->label()), mb_strtolower($termino)))
                ->map(fn (AccionLogActividad $accion) => $accion->value)
                ->all();

            $resultadosCoincidentes = collect(ResultadoLogActividad::cases())
                ->filter(fn (ResultadoLogActividad $resultado) => str_contains(mb_strtolower($resultado->label()), mb_strtolower($termino)))
                ->map(fn (ResultadoLogActividad $resultado) => $resultado->value)
                ->all();

            $query->where(function ($q) use ($termino, $accionesCoincidentes, $resultadosCoincidentes) {
                $q->where('usuario_nombre', 'like', "%{$termino}%")
                    ->orWhere('descripcion', 'like', "%{$termino}%")
                    ->orWhere('ip_origen', 'like', "%{$termino}%");

                if ($accionesCoincidentes !== []) {
                    $q->orWhereIn('accion', $accionesCoincidentes);
                }

                if ($resultadosCoincidentes !== []) {
                    $q->orWhereIn('resultado', $resultadosCoincidentes);
                }
            });
        }

        $recordsFiltered = (clone $query)->count();

        $columnaOrden = $request->input('order.0.column');
        $direccion = strtolower((string) $request->input('order.0.dir', 'desc')) === 'asc' ? 'asc' : 'desc';
        $dataOrden = $columnaOrden !== null ? $request->input("columns.{$columnaOrden}.data") : null;
        $columnaBd = self::COLUMNAS_ORDENABLES[$dataOrden] ?? 'ocurrido_at';

        $query->orderBy($columnaBd, $direccion);

        if ($columnaBd !== 'ocurrido_at') {
            $query->orderByDesc('ocurrido_at');
        }

        $start = max(0, (int) $request->input('start', 0));
        $length = max(1, (int) $request->input('length', 10));

        $logs = $query->skip($start)->take($length)->get();

        return response()->json([
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $logs->map(fn (LogActividad $log) => [
                'fecha' => $log->ocurrido_at->enZonaTenant()->format('d/m/Y H:i'),
                'usuario_nombre' => $log->usuario_nombre,
                'accion' => $log->accion->value,
                'accion_label' => $log->accion->label(),
                'resultado' => $log->resultado->value,
                'resultado_label' => $log->resultado->label(),
                'ip_origen' => $log->ip_origen,
                'navegador' => AgenteUsuario::label($log->user_agent),
                'ubicacion' => GeolocalizadorIp::ubicacion($log->ip_origen),
                'entidad_tipo' => $log->entidad_tipo?->value,
                'entidad_label' => $log->entidad_tipo?->label(),
                'descripcion' => $log->descripcion,
            ])->values(),
        ]);
    }
}
