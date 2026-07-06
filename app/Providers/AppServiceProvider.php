<?php

namespace App\Providers;

use App\Enums\UserRole;
use App\Models\User;
use App\Support\ConfigTenant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Illuminate\Auth\Events\{Login,Logout,Failed,Lockout} -> LogAuthenticationActivity ya
        // quedan enganchados por el auto-discovery de eventos de Laravel (los métodos handle*
        // están type-hinted con la clase del evento): registrarlos aquí también los disparaba
        // dos veces por request (detectado al añadir el registro en logs_actividad — feature 021).

        // Gestión de miembros/alertas/correcciones/informe global de fichajes (024): solo Admin.
        Gate::define('gestiona-fichajes', fn (User $user) => $user->rol === UserRole::Admin);

        // Convierte un datetime (guardado en UTC) a la zona horaria del tenant activo, solo para
        // mostrarlo. Azúcar para vistas/JSON dentro de contexto de tenant: `$fecha->enZonaTenant()`.
        // Sin tenant activo (contexto central) cae al default. Solo para datetimes reales, no para
        // campos `date` puros (ver ConfigTenant::paraMostrar).
        Carbon::macro('enZonaTenant', function () {
            /** @var Carbon $this */
            $tenant = function_exists('tenant') ? tenant() : null;
            $zona = $tenant
                ? ConfigTenant::zonaHoraria($tenant->getTenantKey())
                : ConfigTenant::DEFAULT_ZONA_HORARIA;

            return $this->copy()->setTimezone($zona);
        });
    }
}
