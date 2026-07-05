<?php

namespace App\Http\Controllers;

use App\Enums\EstadoCampana;
use App\Enums\EstadoDestinatario;
use App\Exceptions\EmailNoConfiguradoException;
use App\Http\Requests\EnviarTandaRequest;
use App\Http\Requests\StoreCampanaRequest;
use App\Mail\CampanaMail;
use App\Models\Campana;
use App\Models\CampanaDestinatario;
use App\Models\Cliente;
use App\Models\PlantillaEmail;
use App\Services\TenantMailer;
use App\Support\EmailTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CampanaController extends Controller
{
    /**
     * Tamaño de tanda (research D2): 8 destinatarios por request de envío.
     */
    public const TAMANO_TANDA = 8;

    public function index(Request $request): View|JsonResponse
    {
        if ($request->wantsJson()) {
            $campanas = Campana::query()->orderByDesc('created_at')->get();

            return response()->json([
                'data' => $campanas->map(fn (Campana $campana) => [
                    'id' => $campana->id,
                    'asunto' => $campana->asunto,
                    'estado' => $campana->estado->value,
                    'total' => $campana->total_destinatarios,
                    'enviados' => $campana->enviados,
                    'fallidos' => $campana->fallidos,
                    'fecha' => $campana->created_at?->format('d/m/Y H:i'),
                    'show_url' => route('campanas.show', $campana),
                ])->values(),
                'totales' => [
                    'total' => $campanas->count(),
                    'enviados' => (int) $campanas->sum('enviados'),
                    'fallidos' => (int) $campanas->sum('fallidos'),
                ],
            ]);
        }

        return view('campanas.index');
    }

    public function create(): View
    {
        return view('campanas.create', [
            'plantillas' => PlantillaEmail::query()->where('activa', true)->orderBy('titulo')->get(),
            'clientes' => Cliente::query()->orderBy('nombre')->get(),
            'emailConfigurado' => EmailTenant::estaConfigurado(tenant()->getTenantKey()),
        ]);
    }

    public function store(StoreCampanaRequest $request): RedirectResponse|JsonResponse
    {
        if (! EmailTenant::estaConfigurado(tenant()->getTenantKey())) {
            $mensaje = 'Configura primero tu servidor de correo en Configuración → Email.';

            if ($request->wantsJson()) {
                return response()->json(['message' => $mensaje], 422);
            }

            return redirect()->route('campanas.create')->with('error', $mensaje);
        }

        $datos = $request->validated();

        $campana = Campana::create([
            'user_id' => $request->user()?->id,
            'plantilla_email_id' => $datos['plantilla_email_id'] ?? null,
            'asunto' => $datos['asunto'],
            'cuerpo' => $datos['cuerpo'],
            'estado' => EstadoCampana::Borrador,
        ]);

        $clienteIds = array_values(array_unique($datos['cliente_ids']));
        $clientes = Cliente::query()->whereIn('id', $clienteIds)->get();

        foreach ($clientes as $cliente) {
            $tieneEmail = ! empty($cliente->email);

            $campana->destinatarios()->create([
                'tenant_id' => $campana->tenant_id,
                'cliente_id' => $cliente->id,
                'email' => $cliente->email,
                'estado' => $tieneEmail ? EstadoDestinatario::Pendiente : EstadoDestinatario::Fallido,
                'error' => $tieneEmail ? null : 'Sin email',
            ]);
        }

        $this->recalcular($campana);

        if ($request->wantsJson()) {
            $pendientes = $campana->destinatarios()
                ->where('estado', EstadoDestinatario::Pendiente)
                ->pluck('id')
                ->values();

            return response()->json([
                'campana_id' => $campana->id,
                'show_url' => route('campanas.show', $campana),
                'destinatario_ids' => $pendientes,
                'campana' => [
                    'estado' => $campana->estado->value,
                    'enviados' => $campana->enviados,
                    'fallidos' => $campana->fallidos,
                    'total' => $campana->total_destinatarios,
                ],
            ], 201);
        }

        return redirect()->route('campanas.show', $campana)
            ->with('success', 'Campaña creada. Ya puedes lanzar el envío.');
    }

    public function show(string $campana): View
    {
        $campana = Campana::query()->with(['destinatarios.cliente'])->findOrFail($campana);

        return view('campanas.show', [
            'campana' => $campana,
            'tamanoTanda' => self::TAMANO_TANDA,
        ]);
    }

    public function enviarTanda(EnviarTandaRequest $request, string $campana): JsonResponse
    {
        $campana = Campana::query()->findOrFail($campana);

        try {
            $tenantMailer = new TenantMailer(tenant()->getTenantKey());
        } catch (EmailNoConfiguradoException) {
            return response()->json([
                'message' => 'Configura primero tu servidor de correo en Configuración → Email.',
            ], 422);
        }

        $destinatarios = $campana->destinatarios()
            ->whereIn('id', $request->validated()['destinatario_ids'])
            ->where('estado', EstadoDestinatario::Pendiente)
            ->get();

        $resultados = [];

        foreach ($destinatarios as $destinatario) {
            if (empty($destinatario->email)) {
                $destinatario->update([
                    'estado' => EstadoDestinatario::Fallido,
                    'error' => 'Sin email',
                ]);
                $resultados[] = $this->resultado($destinatario);

                continue;
            }

            $mailable = (new CampanaMail($campana))
                ->from($tenantMailer->remitente(), $tenantMailer->remitenteNombre());

            if ($tenantMailer->responderA()) {
                $mailable->replyTo($tenantMailer->responderA());
            }

            try {
                $tenantMailer->mailer()->to($destinatario->email)->send($mailable);

                $destinatario->update([
                    'estado' => EstadoDestinatario::Enviado,
                    'error' => null,
                    'enviado_at' => now(),
                ]);
            } catch (\Throwable $e) {
                $destinatario->update([
                    'estado' => EstadoDestinatario::Fallido,
                    'error' => Str::limit($e->getMessage(), 500, ''),
                ]);
            }

            $resultados[] = $this->resultado($destinatario);
        }

        if ($campana->enviada_at === null) {
            $campana->enviada_at = now();
            $campana->save();
        }

        $this->recalcular($campana);

        return response()->json([
            'resultados' => $resultados,
            'campana' => [
                'estado' => $campana->estado->value,
                'enviados' => $campana->enviados,
                'fallidos' => $campana->fallidos,
                'total' => $campana->total_destinatarios,
            ],
        ]);
    }

    public function reintentar(string $campana): JsonResponse
    {
        $campana = Campana::query()->findOrFail($campana);

        $destinatarios = $campana->destinatarios()
            ->where('estado', EstadoDestinatario::Fallido)
            ->whereNotNull('email')
            ->get();

        foreach ($destinatarios as $destinatario) {
            $destinatario->update([
                'estado' => EstadoDestinatario::Pendiente,
                'error' => null,
            ]);
        }

        $this->recalcular($campana);

        return response()->json([
            'destinatario_ids' => $destinatarios->pluck('id')->values(),
        ]);
    }

    /**
     * Recalcula contadores cache y estado de la campaña desde sus destinatarios
     * (fuente de verdad). No toca `enviada_at`.
     */
    private function recalcular(Campana $campana): void
    {
        $total = $campana->destinatarios()->count();
        $enviados = $campana->destinatarios()->where('estado', EstadoDestinatario::Enviado)->count();
        $fallidos = $campana->destinatarios()->where('estado', EstadoDestinatario::Fallido)->count();
        $pendientes = $total - $enviados - $fallidos;

        if ($pendientes === 0) {
            $estado = EstadoCampana::Finalizada;
        } elseif ($campana->enviada_at !== null) {
            $estado = EstadoCampana::EnCurso;
        } else {
            $estado = EstadoCampana::Borrador;
        }

        $campana->update([
            'total_destinatarios' => $total,
            'enviados' => $enviados,
            'fallidos' => $fallidos,
            'estado' => $estado,
        ]);
    }

    /**
     * @return array{id: int, cliente_id: int, estado: string, error: string|null}
     */
    private function resultado(CampanaDestinatario $destinatario): array
    {
        return [
            'id' => $destinatario->id,
            'cliente_id' => $destinatario->cliente_id,
            'estado' => $destinatario->estado->value,
            'error' => $destinatario->error,
        ];
    }
}
