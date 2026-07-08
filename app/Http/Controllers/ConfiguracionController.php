<?php

namespace App\Http\Controllers;

use App\Enums\AccionLogActividad;
use App\Enums\EntidadLogActividad;
use App\Exceptions\CertificadoInvalidoException;
use App\Exceptions\EmailNoConfiguradoException;
use App\Http\Requests\UpdateAparienciaRequest;
use App\Http\Requests\UpdateEmailRequest;
use App\Mail\EmailPrueba;
use App\Models\Configuracion;
use App\Models\User;
use App\Services\RegistradorActividad;
use App\Services\TenantMailer;
use App\Support\AparienciaTenant;
use App\Support\ArchivosTenant;
use App\Support\CertificadoTenant;
use App\Support\ConfigCrm;
use App\Support\ConfigFichajes;
use App\Support\ConfigTenant;
use App\Support\EmailTenant;
use App\Support\RetencionGeoTenant;
use App\Support\RetencionMiembroTenant;
use App\Support\TopeSimplificada;
use App\Support\VerificadorVies;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class ConfiguracionController extends Controller
{
    public function __construct(
        private readonly RegistradorActividad $registradorActividad,
    ) {}

    public function show(): View
    {
        $tenantId = tenant()->getTenantKey();

        $email = EmailTenant::valores($tenantId);
        unset($email['smtp_password']);

        return view('configuracion.index', [
            'colores' => AparienciaTenant::coloresEfectivos($tenantId),
            'extras' => AparienciaTenant::extrasEfectivos($tenantId),
            'logoPath' => tenant()->logo_path,
            'logoMiniPath' => tenant()->logo_mini_path,
            'loginLogoPath' => tenant()->login_logo_path,
            'loginImagenPath' => tenant()->login_imagen_path,
            'logoFacturacionPath' => tenant()->logo_facturacion_path,
            'faviconPath' => tenant()->favicon_path,
            'simplificadaTopeAmpliado' => app(TopeSimplificada::class)->sectorAmpliado(),
            'email' => $email,
            'emailTienePassword' => EmailTenant::tienePasswordGuardada($tenantId),
            'archivosLimiteMb' => ArchivosTenant::limiteMb($tenantId),
            'certificado' => CertificadoTenant::metadatos($tenantId),
            'fichajesConfig' => [
                'retencion_geo_dias' => RetencionGeoTenant::dias($tenantId),
                'retencion_casa_dias' => RetencionMiembroTenant::dias($tenantId),
                'geofencing_bloqueante' => ConfigFichajes::geofencingBloqueante($tenantId),
                'registrar_pausas' => ConfigFichajes::registrarPausas($tenantId),
                'tolerancia_retraso_min' => ConfigFichajes::toleranciaRetrasoMin($tenantId),
                'tolerancia_exceso_min' => ConfigFichajes::toleranciaExcesoMin($tenantId),
            ],
            'generalConfig' => [
                'zona_horaria' => ConfigTenant::zonaHoraria($tenantId),
            ],
            'zonasHorariasDisponibles' => ConfigTenant::ZONAS_HORARIAS_DISPONIBLES,
            'crmConfig' => [
                'asignacion_estrategia' => ConfigCrm::estrategiaAsignacion($tenantId)->value,
                'asignacion_comerciales' => ConfigCrm::comercialesAsignacion($tenantId),
                'retencion_dias' => ConfigCrm::retencionDias($tenantId),
                'presupuesto_dias_validez' => ConfigCrm::diasValidezPresupuesto($tenantId),
            ],
            'comercialesDisponibles' => User::where('tenant_id', $tenantId)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function verificarVies(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'nif_iva' => ['required', 'string'],
            'pais' => ['required', 'string', 'size:2'],
        ]);

        $resultado = VerificadorVies::verificar($datos['nif_iva'], $datos['pais']);

        return response()->json($resultado);
    }

    public function updateCertificado(Request $request): RedirectResponse|JsonResponse
    {
        $tenantId = tenant()->getTenantKey();

        $datos = $request->validate([
            'certificado' => ['required', 'file'],
            'password' => ['required', 'string'],
        ]);

        try {
            CertificadoTenant::guardar($datos['certificado'], $datos['password'], $tenantId);
        } catch (CertificadoInvalidoException $e) {
            if ($request->wantsJson()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return redirect()->route('configuracion.show')->with('error', $e->getMessage());
        }

        $this->registradorActividad->registrar(
            auth()->user(),
            AccionLogActividad::Modificacion,
            EntidadLogActividad::Configuracion,
            null,
            'Actualizó el certificado de firma Facturae',
        );

        $mensaje = 'Certificado guardado correctamente.';

        if ($request->wantsJson()) {
            return response()->json(['message' => $mensaje]);
        }

        return redirect()->route('configuracion.show')->with('success', $mensaje);
    }

    public function updateEmail(UpdateEmailRequest $request): RedirectResponse|JsonResponse
    {
        $tenantId = tenant()->getTenantKey();
        $datos = $request->validated();

        $campos = [
            'smtp_host' => [EmailTenant::CLAVE_SMTP_HOST, 'string'],
            'smtp_port' => [EmailTenant::CLAVE_SMTP_PORT, 'integer'],
            'smtp_encryption' => [EmailTenant::CLAVE_SMTP_ENCRYPTION, 'string'],
            'smtp_usuario' => [EmailTenant::CLAVE_SMTP_USUARIO, 'string'],
            'remitente' => [EmailTenant::CLAVE_REMITENTE, 'string'],
            'remitente_nombre' => [EmailTenant::CLAVE_REMITENTE_NOMBRE, 'string'],
            'responder_a' => [EmailTenant::CLAVE_RESPONDER_A, 'string'],
        ];

        foreach ($campos as $campo => [$clave, $tipo]) {
            Configuracion::query()->updateOrCreate(
                ['tenant_id' => $tenantId, 'clave' => $clave],
                ['valor' => (string) ($datos[$campo] ?? ''), 'tipo' => $tipo, 'grupo' => 'email']
            );
        }

        if (! empty($datos['smtp_password'])) {
            Configuracion::query()->updateOrCreate(
                ['tenant_id' => $tenantId, 'clave' => EmailTenant::CLAVE_SMTP_PASSWORD],
                ['valor' => Crypt::encryptString($datos['smtp_password']), 'tipo' => 'string', 'grupo' => 'email']
            );
        }

        $this->registradorActividad->registrar(
            auth()->user(),
            AccionLogActividad::Modificacion,
            EntidadLogActividad::Configuracion,
            null,
            'Actualizó la configuración de email',
        );

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Configuración de email guardada correctamente.']);
        }

        return redirect()->route('configuracion.show')->with('success', 'Configuración de email guardada correctamente.');
    }

    public function enviarPrueba(Request $request): RedirectResponse|JsonResponse
    {
        $tenantId = tenant()->getTenantKey();

        try {
            $tenantMailer = new TenantMailer($tenantId);
            $destinatario = $request->user()->email;

            $mailable = (new EmailPrueba(tenant()->nombre_comercial))
                ->from($tenantMailer->remitente(), $tenantMailer->remitenteNombre());

            if ($tenantMailer->responderA()) {
                $mailable->replyTo($tenantMailer->responderA());
            }

            $tenantMailer->mailer()->to($destinatario)->send($mailable);
        } catch (EmailNoConfiguradoException $e) {
            $mensaje = 'Configura primero los datos de tu servidor de correo.';

            if ($request->wantsJson()) {
                return response()->json(['message' => $mensaje], 422);
            }

            return redirect()->route('configuracion.show')->with('error', $mensaje);
        } catch (TransportExceptionInterface|\Throwable $e) {
            $mensaje = 'No se pudo conectar con el servidor de correo. Revisa host, puerto y credenciales.';

            if ($request->wantsJson()) {
                return response()->json(['message' => $mensaje], 502);
            }

            return redirect()->route('configuracion.show')->with('error', $mensaje);
        }

        $mensaje = "Email de prueba enviado a {$destinatario}.";

        if ($request->wantsJson()) {
            return response()->json(['message' => $mensaje]);
        }

        return redirect()->route('configuracion.show')->with('success', $mensaje);
    }

    public function updateFacturacion(Request $request): RedirectResponse|JsonResponse
    {
        $tenantId = tenant()->getTenantKey();

        $ampliado = $request->boolean('simplificada_tope_ampliado');

        Configuracion::query()->updateOrCreate(
            ['tenant_id' => $tenantId, 'clave' => TopeSimplificada::CLAVE],
            ['valor' => $ampliado ? '1' : '0', 'tipo' => 'boolean', 'grupo' => 'facturacion']
        );

        $this->registradorActividad->registrar(
            auth()->user(),
            AccionLogActividad::Modificacion,
            EntidadLogActividad::Configuracion,
            null,
            'Actualizó los datos de facturación',
        );

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Configuración de facturación guardada correctamente.']);
        }

        return redirect()->route('configuracion.show')->with('success', 'Configuración de facturación guardada correctamente.');
    }

    public function updateFichajes(Request $request): RedirectResponse|JsonResponse
    {
        $tenantId = tenant()->getTenantKey();

        $datos = $request->validate([
            'retencion_geo_dias' => ['required', 'integer', 'min:1'],
            'retencion_casa_dias' => ['required', 'integer', 'min:1'],
            'tolerancia_retraso_min' => ['required', 'integer', 'min:0'],
            'tolerancia_exceso_min' => ['required', 'integer', 'min:0'],
        ]);

        Configuracion::query()->updateOrCreate(
            ['tenant_id' => $tenantId, 'clave' => RetencionGeoTenant::CLAVE],
            ['valor' => (string) $datos['retencion_geo_dias'], 'tipo' => 'integer', 'grupo' => 'fichajes']
        );

        Configuracion::query()->updateOrCreate(
            ['tenant_id' => $tenantId, 'clave' => RetencionMiembroTenant::CLAVE],
            ['valor' => (string) $datos['retencion_casa_dias'], 'tipo' => 'integer', 'grupo' => 'fichajes']
        );

        Configuracion::query()->updateOrCreate(
            ['tenant_id' => $tenantId, 'clave' => ConfigFichajes::CLAVE_GEOFENCING_BLOQUEANTE],
            ['valor' => $request->boolean('geofencing_bloqueante') ? '1' : '0', 'tipo' => 'boolean', 'grupo' => 'fichajes']
        );

        Configuracion::query()->updateOrCreate(
            ['tenant_id' => $tenantId, 'clave' => ConfigFichajes::CLAVE_REGISTRAR_PAUSAS],
            ['valor' => $request->boolean('registrar_pausas') ? '1' : '0', 'tipo' => 'boolean', 'grupo' => 'fichajes']
        );

        Configuracion::query()->updateOrCreate(
            ['tenant_id' => $tenantId, 'clave' => ConfigFichajes::CLAVE_TOLERANCIA_RETRASO_MIN],
            ['valor' => (string) $datos['tolerancia_retraso_min'], 'tipo' => 'integer', 'grupo' => 'fichajes']
        );

        Configuracion::query()->updateOrCreate(
            ['tenant_id' => $tenantId, 'clave' => ConfigFichajes::CLAVE_TOLERANCIA_EXCESO_MIN],
            ['valor' => (string) $datos['tolerancia_exceso_min'], 'tipo' => 'integer', 'grupo' => 'fichajes']
        );

        $this->registradorActividad->registrar(
            auth()->user(),
            AccionLogActividad::Modificacion,
            EntidadLogActividad::Configuracion,
            null,
            'Actualizó la configuración de fichajes',
        );

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Configuración de fichajes guardada correctamente.']);
        }

        return redirect()->route('configuracion.show')->with('success', 'Configuración de fichajes guardada correctamente.');
    }

    public function updateCrm(Request $request): RedirectResponse|JsonResponse
    {
        $tenantId = tenant()->getTenantKey();

        $datos = $request->validate([
            'asignacion_estrategia' => ['required', 'string', 'in:manual,round_robin'],
            'asignacion_comerciales' => ['nullable', 'array'],
            'asignacion_comerciales.*' => ['integer'],
            'retencion_dias' => ['required', 'integer', 'min:1'],
            'presupuesto_dias_validez' => ['required', 'integer', 'min:1'],
        ]);

        Configuracion::query()->updateOrCreate(
            ['tenant_id' => $tenantId, 'clave' => ConfigCrm::CLAVE_ASIGNACION_ESTRATEGIA],
            ['valor' => $datos['asignacion_estrategia'], 'tipo' => 'string', 'grupo' => 'crm']
        );

        Configuracion::query()->updateOrCreate(
            ['tenant_id' => $tenantId, 'clave' => ConfigCrm::CLAVE_ASIGNACION_COMERCIALES],
            ['valor' => json_encode(array_values($datos['asignacion_comerciales'] ?? [])), 'tipo' => 'json', 'grupo' => 'crm']
        );

        Configuracion::query()->updateOrCreate(
            ['tenant_id' => $tenantId, 'clave' => ConfigCrm::CLAVE_RETENCION_DIAS],
            ['valor' => (string) $datos['retencion_dias'], 'tipo' => 'integer', 'grupo' => 'crm']
        );

        Configuracion::query()->updateOrCreate(
            ['tenant_id' => $tenantId, 'clave' => ConfigCrm::CLAVE_DIAS_VALIDEZ_PRESUPUESTO],
            ['valor' => (string) $datos['presupuesto_dias_validez'], 'tipo' => 'integer', 'grupo' => 'crm']
        );

        $this->registradorActividad->registrar(
            auth()->user(),
            AccionLogActividad::Modificacion,
            EntidadLogActividad::Configuracion,
            null,
            'Actualizó la configuración de CRM (leads/presupuestos)',
        );

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Configuración de CRM guardada correctamente.']);
        }

        return redirect()->route('configuracion.show')->with('success', 'Configuración de CRM guardada correctamente.');
    }

    public function updateGeneral(Request $request): RedirectResponse|JsonResponse
    {
        $tenantId = tenant()->getTenantKey();

        $datos = $request->validate([
            'zona_horaria' => ['required', 'string', 'in:'.implode(',', array_keys(ConfigTenant::ZONAS_HORARIAS_DISPONIBLES))],
        ]);

        Configuracion::query()->updateOrCreate(
            ['tenant_id' => $tenantId, 'clave' => ConfigTenant::CLAVE_ZONA_HORARIA],
            ['valor' => $datos['zona_horaria'], 'tipo' => 'string', 'grupo' => 'general']
        );

        $this->registradorActividad->registrar(
            auth()->user(),
            AccionLogActividad::Modificacion,
            EntidadLogActividad::Configuracion,
            null,
            'Actualizó la configuración general',
        );

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Configuración general guardada correctamente.']);
        }

        return redirect()->route('configuracion.show')->with('success', 'Configuración general guardada correctamente.');
    }

    public function update(UpdateAparienciaRequest $request): RedirectResponse|JsonResponse
    {
        $tenant = tenant();
        $tenantId = $tenant->getTenantKey();
        $datos = $request->validated();

        if ($datos['restablecer'] ?? false) {
            Configuracion::query()
                ->where('tenant_id', $tenantId)
                ->where('grupo', 'apariencia')
                ->whereIn('clave', [
                    'apariencia.color_primario',
                    'apariencia.color_secundario',
                    'apariencia.color_topbar',
                    'apariencia.facebook_url',
                    'apariencia.instagram_url',
                    'apariencia.titulo_login',
                ])
                ->delete();

            $this->borrarLogo($tenant, 'logo_path');
            $this->borrarLogo($tenant, 'logo_mini_path');
            $this->borrarLogo($tenant, 'login_logo_path');
            $this->borrarLogo($tenant, 'login_imagen_path');
            $this->borrarLogo($tenant, 'logo_facturacion_path');
            $this->borrarLogo($tenant, 'favicon_path');
            $tenant->update([
                'logo_path' => null,
                'logo_mini_path' => null,
                'login_logo_path' => null,
                'login_imagen_path' => null,
                'logo_facturacion_path' => null,
                'favicon_path' => null,
            ]);
        } else {
            foreach (['color_primario', 'color_secundario', 'color_topbar', 'facebook_url', 'instagram_url', 'titulo_login'] as $campo) {
                if (! empty($datos[$campo])) {
                    Configuracion::query()->updateOrCreate(
                        ['tenant_id' => $tenantId, 'clave' => "apariencia.{$campo}"],
                        ['valor' => $datos[$campo], 'tipo' => 'string', 'grupo' => 'apariencia']
                    );
                }
            }

            if ($request->hasFile('logo')) {
                $this->borrarLogo($tenant, 'logo_path');

                $ruta = $request->file('logo')->store("logos/{$tenantId}", 'public');
                $tenant->update(['logo_path' => $ruta]);
            }

            if ($request->hasFile('logo_mini')) {
                $this->borrarLogo($tenant, 'logo_mini_path');

                $ruta = $request->file('logo_mini')->store("logos/{$tenantId}", 'public');
                $tenant->update(['logo_mini_path' => $ruta]);
            }

            if ($request->hasFile('login_logo')) {
                $this->borrarLogo($tenant, 'login_logo_path');

                $ruta = $request->file('login_logo')->store("logos/{$tenantId}", 'public');
                $tenant->update(['login_logo_path' => $ruta]);
            }

            if ($request->hasFile('login_imagen')) {
                $this->borrarLogo($tenant, 'login_imagen_path');

                $ruta = $request->file('login_imagen')->store("logos/{$tenantId}", 'public');
                $tenant->update(['login_imagen_path' => $ruta]);
            }

            if ($request->hasFile('logo_facturacion')) {
                $this->borrarLogo($tenant, 'logo_facturacion_path');

                $ruta = $request->file('logo_facturacion')->store("logos/{$tenantId}", 'public');
                $tenant->update(['logo_facturacion_path' => $ruta]);
            }

            if ($request->hasFile('favicon')) {
                $this->borrarLogo($tenant, 'favicon_path');

                $ruta = $request->file('favicon')->store("logos/{$tenantId}", 'public');
                $tenant->update(['favicon_path' => $ruta]);
            }
        }

        AparienciaTenant::invalidarCache($tenantId);

        $this->registradorActividad->registrar(
            auth()->user(),
            AccionLogActividad::Modificacion,
            EntidadLogActividad::Configuracion,
            null,
            'Actualizó la apariencia del sistema',
        );

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Apariencia guardada correctamente.']);
        }

        return redirect()->route('configuracion.show')->with('success', 'Apariencia guardada correctamente.');
    }

    public function updateArchivos(Request $request): RedirectResponse|JsonResponse
    {
        $tenantId = tenant()->getTenantKey();

        $datos = $request->validate([
            'limite_mb' => ['required', 'integer', 'min:1', 'max:1024'],
        ]);

        Configuracion::query()->updateOrCreate(
            ['tenant_id' => $tenantId, 'clave' => ArchivosTenant::CLAVE_LIMITE_MB],
            ['valor' => (string) $datos['limite_mb'], 'tipo' => 'integer', 'grupo' => 'archivos']
        );

        $this->registradorActividad->registrar(
            auth()->user(),
            AccionLogActividad::Modificacion,
            EntidadLogActividad::Configuracion,
            null,
            'Actualizó la configuración de archivos',
        );

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Configuración de archivos guardada correctamente.']);
        }

        return redirect()->route('configuracion.show')->with('success', 'Configuración de archivos guardada correctamente.');
    }

    private function borrarLogo($tenant, string $campo): void
    {
        if ($tenant->{$campo}) {
            Storage::disk('public')->delete($tenant->{$campo});
        }
    }
}
