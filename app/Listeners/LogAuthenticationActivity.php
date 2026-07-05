<?php

namespace App\Listeners;

use App\Enums\AccionLogActividad;
use App\Services\RegistradorActividad;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Log;

class LogAuthenticationActivity
{
    public function __construct(
        private readonly RegistradorActividad $registradorActividad,
    ) {}

    public function handleLogin(Login $event): void
    {
        Log::info('auth.login', [
            'user_id' => $event->user->getAuthIdentifier(),
            'email' => $event->user->email,
            'ip' => request()->ip(),
        ]);

        // logs_actividad es siempre por tenant (Assumptions de la spec 021): un super admin
        // autenticado en el dominio central no tiene tenant_id y no genera fila.
        if ($event->user->tenant_id !== null) {
            $this->registradorActividad->registrar($event->user, AccionLogActividad::Login, null, null, 'Inició sesión');
        }
    }

    public function handleLogout(Logout $event): void
    {
        Log::info('auth.logout', [
            'user_id' => $event->user?->getAuthIdentifier(),
            'email' => $event->user?->email,
        ]);

        if ($event->user !== null && $event->user->tenant_id !== null) {
            $this->registradorActividad->registrar($event->user, AccionLogActividad::Logout, null, null, 'Cerró sesión');
        }
    }

    public function handleFailed(Failed $event): void
    {
        Log::warning('auth.failed', [
            'email' => $event->credentials['email'] ?? null,
            'ip' => request()->ip(),
        ]);

        // Registro de accesos (RGPD/LOPDGDD): el intento fallido es siempre por tenant, igual
        // que el login exitoso. Sin contexto de tenant (dominio central) no hay historial al que
        // asociar la fila.
        if (tenancy()->initialized) {
            $email = $event->credentials['email'] ?? 'desconocido';

            $this->registradorActividad->registrarIntentoFallido(
                tenant('id'),
                $email,
                "Intento de inicio de sesión fallido para {$email}",
            );
        }
    }

    public function handleLockout(Lockout $event): void
    {
        Log::warning('auth.lockout', [
            'email' => $event->request->input('email'),
            'ip' => $event->request->ip(),
        ]);
    }
}
