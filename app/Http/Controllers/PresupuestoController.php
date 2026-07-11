<?php

namespace App\Http\Controllers;

use App\Enums\AccionLogActividad;
use App\Enums\EntidadLogActividad;
use App\Enums\EstadoPresupuesto;
use App\Enums\EtapaOportunidad;
use App\Exceptions\EmailNoConfiguradoException;
use App\Exceptions\PresupuestoNoConvertibleException;
use App\Http\Requests\StorePresupuestoRequest;
use App\Http\Requests\UpdatePresupuestoRequest;
use App\Mail\PresupuestoMail;
use App\Models\Cliente;
use App\Models\Lead;
use App\Models\Oportunidad;
use App\Models\Presupuesto;
use App\Services\ConversorPresupuestoFactura;
use App\Services\RegistradorActividad;
use App\Services\RegistroPresupuesto;
use App\Services\TenantMailer;
use App\Support\ConfigCrm;
use App\Support\EmailTenant;
use App\Support\TiposImpositivos;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class PresupuestoController extends Controller
{
    public function __construct(
        private readonly RegistroPresupuesto $registro,
        private readonly ConversorPresupuestoFactura $conversor,
        private readonly RegistradorActividad $registradorActividad,
    ) {}

    public function index(Request $request): View|JsonResponse
    {
        if ($request->wantsJson()) {
            $presupuestos = Presupuesto::with(['cliente', 'lead', 'lineas'])->orderByDesc('fecha_emision')->get();

            return response()->json([
                'data' => $presupuestos->map(fn (Presupuesto $presupuesto) => [
                    'id' => $presupuesto->id,
                    'numero' => $presupuesto->numero,
                    'receptor' => $presupuesto->receptor_nombre,
                    'receptor_email' => $presupuesto->cliente?->email ?? $presupuesto->lead?->email,
                    'estado' => $presupuesto->estado->value,
                    'estado_label' => $presupuesto->estado->label(),
                    'fecha_emision' => $presupuesto->fecha_emision?->format('d/m/Y'),
                    'total' => (float) $presupuesto->total,
                    'es_editable' => $presupuesto->estado->esEditable(),
                    'es_eliminable' => $presupuesto->estado !== EstadoPresupuesto::Facturado,
                    'pdf_url' => route('presupuestos.pdf', $presupuesto),
                    'edit_url' => route('presupuestos.edit', $presupuesto),
                    'delete_url' => route('presupuestos.destroy', $presupuesto),
                    'enviar_url' => route('presupuestos.enviar', $presupuesto),
                    'estado_url' => route('presupuestos.estado', $presupuesto),
                    'convertir_url' => route('presupuestos.convertir', $presupuesto),
                    'puede_generar_albaran' => $presupuesto->estado === EstadoPresupuesto::Aceptado
                        && $presupuesto->lineas->contains(fn ($linea) => $linea->cantidadPendiente() > 0),
                    'generar_albaran_url' => route('albaranes.create', ['presupuesto_id' => $presupuesto->id]),
                ]),
                'totales' => [
                    'total' => Presupuesto::count(),
                    'pendientes' => Presupuesto::where('estado', EstadoPresupuesto::Enviado)->count(),
                    'importe_aceptado' => (float) Presupuesto::where('estado', EstadoPresupuesto::Aceptado)->sum('total'),
                ],
                'email_configurado' => EmailTenant::estaConfigurado(tenant()->getTenantKey()),
            ]);
        }

        return view('presupuestos.index');
    }

    public function create(Request $request): View
    {
        $oportunidad = $request->query('oportunidad_id') ? Oportunidad::find($request->query('oportunidad_id')) : null;
        $cliente = $request->query('cliente_id') ? Cliente::find($request->query('cliente_id')) : null;
        $lead = $request->query('lead_id') ? Lead::find($request->query('lead_id')) : null;

        return view('presupuestos.create', [
            'presupuesto' => null,
            'oportunidadPreseleccionada' => $oportunidad,
            'clientePreseleccionado' => $cliente ?? $oportunidad?->cliente,
            'leadPreseleccionado' => $lead ?? $oportunidad?->lead,
            'clientes' => Cliente::orderBy('nombre')->get(),
            'leads' => Lead::whereNotIn('estado', ['convertido'])->orderBy('nombre')->get(),
            'lineasIniciales' => [],
            'regimen' => TiposImpositivos::payloadVista(tenant()->regimen_impositivo),
            'diasValidez' => ConfigCrm::diasValidezPresupuesto(tenant()->id),
        ]);
    }

    public function store(StorePresupuestoRequest $request): RedirectResponse|JsonResponse
    {
        $presupuesto = $this->registro->guardar($request->validated());

        $this->registradorActividad->registrar(
            auth()->user(),
            AccionLogActividad::Alta,
            EntidadLogActividad::Presupuesto,
            $presupuesto->id,
            "Creó el presupuesto {$presupuesto->numero}",
        );

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Presupuesto creado correctamente.', 'id' => $presupuesto->id], 201);
        }

        return redirect()->route('presupuestos.index')->with('success', 'Presupuesto creado correctamente.');
    }

    public function edit(string $presupuesto): View
    {
        $presupuesto = Presupuesto::with('lineas')->findOrFail($presupuesto);

        if (! $presupuesto->estado->esEditable()) {
            abort(403, 'Solo se pueden editar presupuestos en borrador.');
        }

        return view('presupuestos.create', [
            'presupuesto' => $presupuesto,
            'oportunidadPreseleccionada' => $presupuesto->oportunidad,
            'clientePreseleccionado' => $presupuesto->cliente,
            'leadPreseleccionado' => $presupuesto->lead,
            'clientes' => Cliente::orderBy('nombre')->get(),
            'leads' => Lead::whereNotIn('estado', ['convertido'])->orderBy('nombre')->get(),
            'lineasIniciales' => $presupuesto->lineas->map(fn ($linea) => [
                'articulo_id' => $linea->articulo_id,
                'concepto' => $linea->concepto,
                'unidad' => $linea->unidad,
                'cantidad' => (float) $linea->cantidad,
                'precio_unitario' => (float) $linea->precio_unitario,
                'descuento_porcentaje' => $linea->descuento_porcentaje !== null ? (float) $linea->descuento_porcentaje : null,
                'tipo_impositivo' => (float) $linea->tipo_impositivo,
            ])->values(),
            'regimen' => TiposImpositivos::payloadVista(tenant()->regimen_impositivo),
            'diasValidez' => ConfigCrm::diasValidezPresupuesto(tenant()->id),
        ]);
    }

    public function update(UpdatePresupuestoRequest $request, string $presupuesto): RedirectResponse|JsonResponse
    {
        $presupuesto = Presupuesto::findOrFail($presupuesto);

        if (! $presupuesto->estado->esEditable()) {
            if ($request->wantsJson()) {
                return response()->json(['message' => 'Solo se pueden editar presupuestos en borrador.'], 422);
            }

            abort(403, 'Solo se pueden editar presupuestos en borrador.');
        }

        $this->registro->guardar($request->validated(), $presupuesto);

        $this->registradorActividad->registrar(
            auth()->user(),
            AccionLogActividad::Modificacion,
            EntidadLogActividad::Presupuesto,
            $presupuesto->id,
            "Modificó el presupuesto {$presupuesto->numero}",
        );

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Presupuesto actualizado correctamente.']);
        }

        return redirect()->route('presupuestos.index')->with('success', 'Presupuesto actualizado correctamente.');
    }

    public function destroy(Request $request, string $presupuesto): RedirectResponse|JsonResponse
    {
        $presupuesto = Presupuesto::findOrFail($presupuesto);

        if ($presupuesto->estado === EstadoPresupuesto::Facturado) {
            abort(403, 'No se puede eliminar un presupuesto ya facturado.');
        }

        $numero = $presupuesto->numero;
        $presupuesto->delete();

        $this->registradorActividad->registrar(
            auth()->user(),
            AccionLogActividad::Baja,
            EntidadLogActividad::Presupuesto,
            null,
            "Eliminó el presupuesto {$numero}",
        );

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Presupuesto eliminado correctamente.']);
        }

        return redirect()->route('presupuestos.index')->with('success', 'Presupuesto eliminado correctamente.');
    }

    public function actualizarEstado(Request $request, string $presupuesto): RedirectResponse|JsonResponse
    {
        $presupuesto = Presupuesto::findOrFail($presupuesto);

        $datos = $request->validate([
            'estado' => ['required', 'string', 'in:enviado,aceptado,rechazado,caducado'],
        ]);

        $transicionesValidas = [
            'borrador' => ['enviado'],
            'enviado' => ['aceptado', 'rechazado', 'caducado'],
        ];

        $estadoActual = $presupuesto->estado->value;
        $estadoNuevo = $datos['estado'];

        if (! in_array($estadoNuevo, $transicionesValidas[$estadoActual] ?? [], true)) {
            $mensaje = "No se puede pasar de «{$presupuesto->estado->label()}» a este estado.";

            if ($request->wantsJson()) {
                return response()->json(['message' => $mensaje], 422);
            }

            return redirect()->back()->with('error', $mensaje);
        }

        $atributos = ['estado' => $estadoNuevo];
        if ($estadoNuevo === 'enviado') {
            $atributos['fecha_envio'] = now();
        }

        $presupuesto->update($atributos);

        if ($estadoNuevo === 'aceptado' && $presupuesto->oportunidad && ! $presupuesto->oportunidad->etapa->esTerminal()) {
            $presupuesto->oportunidad->update(['etapa' => EtapaOportunidad::Ganada, 'cerrada_at' => now()]);
        }

        $this->registradorActividad->registrar(
            auth()->user(),
            AccionLogActividad::Modificacion,
            EntidadLogActividad::Presupuesto,
            $presupuesto->id,
            "Cambió el presupuesto {$presupuesto->numero} a estado {$estadoNuevo}",
        );

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Estado actualizado correctamente.',
                'estado' => $presupuesto->estado->value,
                'estado_label' => $presupuesto->estado->label(),
            ]);
        }

        return redirect()->route('presupuestos.index')->with('success', 'Estado actualizado correctamente.');
    }

    public function convertir(Request $request, string $presupuesto): RedirectResponse|JsonResponse
    {
        $presupuesto = Presupuesto::findOrFail($presupuesto);

        try {
            $factura = $this->conversor->convertir($presupuesto);
        } catch (PresupuestoNoConvertibleException $e) {
            if ($request->wantsJson()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return redirect()->back()->with('error', $e->getMessage());
        }

        $this->registradorActividad->registrar(
            auth()->user(),
            AccionLogActividad::Modificacion,
            EntidadLogActividad::Presupuesto,
            $presupuesto->id,
            "Convirtió el presupuesto {$presupuesto->numero} en la factura borrador #{$factura->id}",
        );

        return redirect()->route('facturas.edit', $factura)->with('success', 'Presupuesto convertido en factura borrador.');
    }

    public function pdf(string $presupuesto): Response
    {
        $presupuesto = Presupuesto::with(['lineas', 'cliente', 'tenant'])->findOrFail($presupuesto);

        $pdf = Pdf::loadView('presupuestos.pdf', ['presupuesto' => $presupuesto]);

        return $pdf->stream($presupuesto->numero.'.pdf');
    }

    public function enviar(Request $request, string $presupuesto): RedirectResponse|JsonResponse
    {
        $presupuesto = Presupuesto::with(['lineas', 'cliente', 'lead', 'tenant'])->findOrFail($presupuesto);

        $request->validate(['destinatario' => ['required', 'email']]);
        $destinatario = $request->string('destinatario')->toString();

        try {
            $tenantMailer = new TenantMailer(tenant()->getTenantKey());
        } catch (EmailNoConfiguradoException $e) {
            $mensaje = 'Configura primero tu correo.';

            if ($request->wantsJson()) {
                return response()->json(['message' => $mensaje], 422);
            }

            return redirect()->back()->with('error', $mensaje);
        }

        $pdf = Pdf::loadView('presupuestos.pdf', ['presupuesto' => $presupuesto])->output();

        $mailable = (new PresupuestoMail($presupuesto, $pdf))
            ->from($tenantMailer->remitente(), $tenantMailer->remitenteNombre());

        try {
            $tenantMailer->mailer()->to($destinatario)->send($mailable);
        } catch (\Throwable $e) {
            $mensaje = 'No se pudo enviar el correo. Revisa la configuración de tu servidor de email.';

            if ($request->wantsJson()) {
                return response()->json(['message' => $mensaje], 502);
            }

            return redirect()->back()->with('error', $mensaje);
        }

        $mensaje = "Presupuesto enviado a {$destinatario}.";

        if ($request->wantsJson()) {
            return response()->json(['message' => $mensaje]);
        }

        return redirect()->back()->with('success', $mensaje);
    }
}
