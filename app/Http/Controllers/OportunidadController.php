<?php

namespace App\Http\Controllers;

use App\Enums\AccionLogActividad;
use App\Enums\EntidadLogActividad;
use App\Enums\EtapaOportunidad;
use App\Http\Requests\StoreOportunidadRequest;
use App\Http\Requests\UpdateOportunidadRequest;
use App\Models\Cliente;
use App\Models\Lead;
use App\Models\Oportunidad;
use App\Models\User;
use App\Services\ConversorLeadCliente;
use App\Services\RegistradorActividad;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OportunidadController extends Controller
{
    public function __construct(
        private readonly ConversorLeadCliente $conversor,
        private readonly RegistradorActividad $registradorActividad,
    ) {}

    public function index(Request $request): View|JsonResponse
    {
        if ($request->wantsJson()) {
            $oportunidades = Oportunidad::with(['lead', 'cliente', 'asignadoA'])->orderByDesc('created_at')->get();

            return response()->json([
                'data' => $oportunidades->map(fn (Oportunidad $oportunidad) => [
                    'id' => $oportunidad->id,
                    'titulo' => $oportunidad->titulo,
                    'etapa' => $oportunidad->etapa->value,
                    'etapa_label' => $oportunidad->etapa->label(),
                    'receptor' => $oportunidad->cliente?->nombre ?? $oportunidad->lead?->nombre,
                    'importe_estimado' => $oportunidad->importe_estimado !== null ? (float) $oportunidad->importe_estimado : null,
                    'asignado_a' => $oportunidad->asignado_a,
                    'asignado_nombre' => $oportunidad->asignadoA?->name,
                    'notas' => $oportunidad->notas,
                    'editable' => ! $oportunidad->etapa->esTerminal(),
                ]),
                'resumen_por_etapa' => $this->resumenPorEtapa(),
            ]);
        }

        $resumenPorEtapa = $this->resumenPorEtapa();

        return view('oportunidades.index', [
            'resumenPorEtapa' => $resumenPorEtapa,
            'totalesGenerales' => [
                'abiertas' => ($resumenPorEtapa['nueva']['total'] ?? 0) + ($resumenPorEtapa['en_negociacion']['total'] ?? 0),
                'importe_pipeline' => ($resumenPorEtapa['nueva']['importe_total'] ?? 0) + ($resumenPorEtapa['en_negociacion']['importe_total'] ?? 0),
                'ganadas' => $resumenPorEtapa['ganada']['total'] ?? 0,
            ],
            'leads' => Lead::whereNotIn('estado', ['convertido'])->orderBy('nombre')->get(),
            'clientes' => Cliente::orderBy('nombre')->get(),
            'comerciales' => User::where('tenant_id', tenant()->id)->orderBy('name')->get(),
        ]);
    }

    private function resumenPorEtapa(): array
    {
        return Oportunidad::query()
            ->selectRaw('etapa, COUNT(*) as total, SUM(importe_estimado) as importe_total')
            ->groupBy('etapa')
            ->get()
            ->keyBy('etapa')
            ->map(fn ($fila) => ['total' => (int) $fila->total, 'importe_total' => (float) $fila->importe_total])
            ->all();
    }

    public function store(StoreOportunidadRequest $request): RedirectResponse|JsonResponse
    {
        $datos = $request->validated();
        $lead = ! empty($datos['lead_id']) ? Lead::find($datos['lead_id']) : null;

        $oportunidad = Oportunidad::create([
            'titulo' => $datos['titulo'],
            'lead_id' => $lead?->id,
            'cliente_id' => $datos['cliente_id'] ?? null,
            'etapa' => EtapaOportunidad::Nueva,
            'importe_estimado' => $datos['importe_estimado'] ?? null,
            'asignado_a' => $datos['asignado_a'] ?? $lead?->asignado_a,
            'notas' => $datos['notas'] ?? null,
        ]);

        $this->registradorActividad->registrar(
            auth()->user(),
            AccionLogActividad::Alta,
            EntidadLogActividad::Oportunidad,
            $oportunidad->id,
            "Creó la oportunidad #{$oportunidad->id} ({$oportunidad->titulo})",
        );

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Oportunidad creada correctamente.', 'id' => $oportunidad->id], 201);
        }

        return redirect()->route('oportunidades.show', $oportunidad)->with('success', 'Oportunidad creada correctamente.');
    }

    public function show(string $oportunidad): View
    {
        $oportunidad = Oportunidad::with(['lead', 'cliente', 'asignadoA', 'presupuestos'])->findOrFail($oportunidad);

        return view('oportunidades.show', ['oportunidad' => $oportunidad]);
    }

    public function update(UpdateOportunidadRequest $request, string $oportunidad): RedirectResponse|JsonResponse
    {
        $oportunidad = Oportunidad::findOrFail($oportunidad);
        $datos = $request->validated();

        $oportunidad->update([
            'titulo' => $datos['titulo'],
            'importe_estimado' => $datos['importe_estimado'] ?? null,
            'asignado_a' => $datos['asignado_a'] ?? null,
            'notas' => $datos['notas'] ?? null,
        ]);

        $this->registradorActividad->registrar(
            auth()->user(),
            AccionLogActividad::Modificacion,
            EntidadLogActividad::Oportunidad,
            $oportunidad->id,
            "Modificó la oportunidad #{$oportunidad->id}",
        );

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Oportunidad actualizada correctamente.']);
        }

        return redirect()->route('oportunidades.show', $oportunidad)->with('success', 'Oportunidad actualizada correctamente.');
    }

    public function actualizarEtapa(Request $request, string $oportunidad): RedirectResponse|JsonResponse
    {
        $oportunidad = Oportunidad::findOrFail($oportunidad);

        $datos = $request->validate([
            'etapa' => ['required', 'string', 'in:nueva,en_negociacion,ganada,perdida'],
            'motivo_perdida' => ['required_if:etapa,perdida', 'nullable', 'string', 'max:255'],
        ]);

        if ($oportunidad->etapa->esTerminal()) {
            $mensaje = 'Esta oportunidad ya está cerrada y no admite más cambios de etapa.';

            if ($request->wantsJson()) {
                return response()->json(['message' => $mensaje], 422);
            }

            return redirect()->back()->with('error', $mensaje);
        }

        $etapaNueva = EtapaOportunidad::from($datos['etapa']);

        $atributos = ['etapa' => $etapaNueva];

        if ($etapaNueva->esTerminal()) {
            $atributos['cerrada_at'] = now();
        }

        if ($etapaNueva === EtapaOportunidad::Perdida) {
            $atributos['motivo_perdida'] = $datos['motivo_perdida'];
        }

        $oportunidad->update($atributos);

        if ($etapaNueva === EtapaOportunidad::Ganada && $oportunidad->lead_id) {
            $lead = $oportunidad->lead;
            if ($lead && $lead->estado->value !== 'convertido') {
                $this->conversor->convertir($lead);
            }
        }

        $this->registradorActividad->registrar(
            auth()->user(),
            AccionLogActividad::Modificacion,
            EntidadLogActividad::Oportunidad,
            $oportunidad->id,
            "Cambió la oportunidad #{$oportunidad->id} a etapa {$etapaNueva->value}",
        );

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Etapa actualizada correctamente.',
                'etapa' => $etapaNueva->value,
                'etapa_label' => $etapaNueva->label(),
                'es_terminal' => $etapaNueva->esTerminal(),
                'motivo_perdida' => $oportunidad->motivo_perdida,
            ]);
        }

        return redirect()->route('oportunidades.show', $oportunidad)->with('success', 'Etapa actualizada correctamente.');
    }

    public function destroy(string $oportunidad): RedirectResponse
    {
        $oportunidad = Oportunidad::findOrFail($oportunidad);
        $oportunidadId = $oportunidad->id;
        $oportunidad->delete();

        $this->registradorActividad->registrar(
            auth()->user(),
            AccionLogActividad::Baja,
            EntidadLogActividad::Oportunidad,
            $oportunidadId,
            "Eliminó la oportunidad #{$oportunidadId}",
        );

        return redirect()->route('oportunidades.index')->with('success', 'Oportunidad eliminada correctamente.');
    }
}
