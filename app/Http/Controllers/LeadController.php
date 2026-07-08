<?php

namespace App\Http\Controllers;

use App\Enums\AccionLogActividad;
use App\Enums\EntidadLogActividad;
use App\Enums\EstadoLead;
use App\Enums\OrigenLead;
use App\Http\Requests\StoreLeadRequest;
use App\Http\Requests\UpdateLeadRequest;
use App\Models\Lead;
use App\Models\User;
use App\Services\AsignadorLeads;
use App\Services\ConversorLeadCliente;
use App\Services\RegistradorActividad;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LeadController extends Controller
{
    public function __construct(
        private readonly AsignadorLeads $asignador,
        private readonly ConversorLeadCliente $conversor,
        private readonly RegistradorActividad $registradorActividad,
    ) {}

    public function index(Request $request): View|JsonResponse
    {
        if ($request->wantsJson()) {
            $filtro = $request->query('filtro', 'todos');

            $leads = Lead::with('asignadoA')
                ->when($filtro === 'mios', fn ($query) => $query->where('asignado_a', auth()->id()))
                ->when($filtro === 'sin_asignar', fn ($query) => $query->whereNull('asignado_a'))
                ->orderByDesc('created_at')
                ->get();

            return response()->json([
                'data' => $leads->map(fn (Lead $lead) => [
                    'id' => $lead->id,
                    'nombre' => $lead->nombre,
                    'empresa' => $lead->empresa,
                    'email' => $lead->email,
                    'telefono' => $lead->telefono,
                    'estado' => $lead->estado->value,
                    'estado_label' => $lead->estado->label(),
                    'origen_label' => $lead->origen->label(),
                    'asignado_a' => $lead->asignado_a,
                    'asignado_nombre' => $lead->asignadoA?->name,
                    'created_at' => $lead->created_at?->toIso8601String(),
                ]),
                'totales' => [
                    'total' => Lead::count(),
                    'sin_asignar' => Lead::whereNull('asignado_a')->count(),
                    'cualificados' => Lead::where('estado', EstadoLead::Cualificado)->count(),
                ],
            ]);
        }

        return view('leads.index', [
            'comerciales' => User::where('tenant_id', tenant()->id)->orderBy('name')->get(),
        ]);
    }

    public function store(StoreLeadRequest $request): RedirectResponse|JsonResponse
    {
        $datos = $request->validated();

        $lead = Lead::create([
            'nombre' => $datos['nombre'],
            'empresa' => $datos['empresa'] ?? null,
            'email' => $datos['email'] ?? null,
            'telefono' => $datos['telefono'] ?? null,
            'estado' => EstadoLead::Nuevo,
            'origen' => OrigenLead::Manual,
            'asignado_a' => $datos['asignado_a'] ?? $this->asignador->asignar(tenant()->id),
            'notas' => $datos['notas'] ?? null,
        ]);

        $this->registradorActividad->registrar(
            auth()->user(),
            AccionLogActividad::Alta,
            EntidadLogActividad::Lead,
            $lead->id,
            "Creó el lead #{$lead->id} ({$lead->nombre})",
        );

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Lead creado correctamente.', 'id' => $lead->id], 201);
        }

        return redirect()->route('leads.index')->with('success', 'Lead creado correctamente.');
    }

    /**
     * Ficha del lead: solo JSON, consumido por el modal "Ver ficha" del listado (no hay página
     * de detalle propia). Una navegación directa (no AJAX) redirige al listado.
     */
    public function show(Request $request, string $lead): JsonResponse|RedirectResponse
    {
        $lead = Lead::with(['notasLead.user', 'oportunidades', 'asignadoA'])->findOrFail($lead);

        if (! $request->wantsJson()) {
            return redirect()->route('leads.index');
        }

        return response()->json([
            'id' => $lead->id,
            'nombre' => $lead->nombre,
            'empresa' => $lead->empresa,
            'email' => $lead->email,
            'telefono' => $lead->telefono,
            'estado' => $lead->estado->value,
            'estado_label' => $lead->estado->label(),
            'origen_label' => $lead->origen->label(),
            'asignado_nombre' => $lead->asignadoA?->name,
            'motivo_descarte' => $lead->motivo_descarte,
            'convertido' => $lead->estado === EstadoLead::Convertido,
            'nueva_oportunidad_url' => route('oportunidades.index', ['lead_id' => $lead->id]),
            'convertir_url' => route('leads.convertir', $lead),
            'notas_url' => route('leads.notas.store', $lead),
            'oportunidades' => $lead->oportunidades->map(fn ($oportunidad) => [
                'titulo' => $oportunidad->titulo,
                'etapa_label' => $oportunidad->etapa->label(),
            ]),
            'notas' => $lead->notasLead->map(fn ($nota) => [
                'tipo_label' => ucfirst($nota->tipo),
                'contenido' => $nota->contenido,
                'autor' => $nota->user?->name ?? 'Sistema',
                'fecha' => $nota->created_at->format('d/m/Y H:i'),
            ]),
        ]);
    }

    public function update(UpdateLeadRequest $request, string $lead): RedirectResponse|JsonResponse
    {
        $lead = Lead::findOrFail($lead);
        $datos = $request->validated();

        $lead->update([
            'nombre' => $datos['nombre'],
            'empresa' => $datos['empresa'] ?? null,
            'email' => $datos['email'] ?? null,
            'telefono' => $datos['telefono'] ?? null,
            'asignado_a' => $datos['asignado_a'] ?? null,
            'estado' => $datos['estado'] ?? $lead->estado->value,
            'motivo_descarte' => $datos['motivo_descarte'] ?? null,
            'notas' => $datos['notas'] ?? $lead->notas,
        ]);

        $this->registradorActividad->registrar(
            auth()->user(),
            AccionLogActividad::Modificacion,
            EntidadLogActividad::Lead,
            $lead->id,
            "Modificó el lead #{$lead->id}",
        );

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Lead actualizado correctamente.']);
        }

        return redirect()->route('leads.index')->with('success', 'Lead actualizado correctamente.');
    }

    public function destroy(Request $request, string $lead): RedirectResponse|JsonResponse
    {
        $lead = Lead::findOrFail($lead);
        $leadId = $lead->id;
        $lead->delete();

        $this->registradorActividad->registrar(
            auth()->user(),
            AccionLogActividad::Baja,
            EntidadLogActividad::Lead,
            $leadId,
            "Eliminó el lead #{$leadId}",
        );

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Lead eliminado correctamente.']);
        }

        return redirect()->route('leads.index')->with('success', 'Lead eliminado correctamente.');
    }

    public function storeNota(Request $request, string $lead): RedirectResponse|JsonResponse
    {
        $lead = Lead::findOrFail($lead);

        $datos = $request->validate([
            'tipo' => ['required', 'string', 'in:nota,llamada,email,reunion'],
            'contenido' => ['required', 'string', 'max:500'],
        ]);

        $nota = $lead->notasLead()->create([
            'user_id' => auth()->id(),
            'tipo' => $datos['tipo'],
            'contenido' => $datos['contenido'],
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Nota añadida correctamente.',
                'nota' => [
                    'tipo_label' => ucfirst($nota->tipo),
                    'contenido' => $nota->contenido,
                    'autor' => auth()->user()->name,
                    'fecha' => $nota->created_at->format('d/m/Y H:i'),
                ],
            ], 201);
        }

        return redirect()->route('leads.index')->with('success', 'Nota añadida correctamente.');
    }

    public function convertir(Request $request, string $lead): RedirectResponse
    {
        $lead = Lead::findOrFail($lead);

        if ($lead->estado === EstadoLead::Convertido) {
            return redirect()->back()->with('error', 'Este lead ya fue convertido a cliente.');
        }

        $datos = $request->validate([
            'nif' => ['nullable', 'string', 'max:15'],
            'razon_social' => ['nullable', 'string', 'max:255'],
            'direccion' => ['nullable', 'string', 'max:255'],
            'cp' => ['nullable', 'string', 'max:10'],
            'ciudad' => ['nullable', 'string', 'max:255'],
            'provincia' => ['nullable', 'string', 'max:255'],
            'forzar' => ['nullable', 'boolean'],
        ]);

        if (! empty($datos['nif']) && empty($datos['forzar'])) {
            $existente = $this->conversor->clienteConNif($datos['nif'], tenant()->id);

            if ($existente) {
                return redirect()->back()->with(
                    'error',
                    "Ya existe el cliente «{$existente->nombre}» con ese NIF. Confirma para crear igualmente un cliente nuevo."
                );
            }
        }

        $cliente = $this->conversor->convertir($lead, $datos);

        $this->registradorActividad->registrar(
            auth()->user(),
            AccionLogActividad::Modificacion,
            EntidadLogActividad::Lead,
            $lead->id,
            "Convirtió el lead #{$lead->id} en el cliente #{$cliente->id}",
        );

        return redirect()->route('clientes.index')->with('success', "Lead convertido en cliente: {$cliente->nombre}.");
    }
}
