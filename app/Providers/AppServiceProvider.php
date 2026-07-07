<?php

namespace App\Providers;

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

        // El super admin central (sin tenant, sin roles spatie) pasa cualquier check de permisos
        // (feature 027, research.md D4). Devuelve null para no cortocircuitar el resto de checks
        // del resto de usuarios; las rutas super_admin.* mantienen además EnsureSuperAdmin.
        Gate::before(fn (User $user) => $user->isSuperAdmin() ? true : null);

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
