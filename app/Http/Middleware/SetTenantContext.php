<?php

namespace App\Http\Middleware;

use App\Support\DominioTenant;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\PermissionRegistrar;
use Stancl\Tenancy\Database\Models\Domain;
use Symfony\Component\HttpFoundation\Response;

class SetTenantContext
{
    /**
     * Inicializa el contexto de tenancy a partir del HOST de la petición (research.md D2),
     * no del usuario autenticado:
     *
     * - Host en central_domains -> contexto central, sin tenant.
     * - Host con registro en `domains` -> inicializa tenancy con ese tenant. Si el tenant no
     *   está activo -> logout forzado (si había sesión) y redirect a login.
     * - Host sin registro ni central -> 404 controlado (no se expone ningún tenant).
     */
    public function handle(Request $request, Closure $next): Response
    {
        $host = DominioTenant::normalizar($request->getHost());

        if (in_array($host, config('tenancy.central_domains'), true)) {
            // En tests (y en general, si el proceso PHP se reutiliza entre requests) el estado de
            // tenancy podría seguir inicializado de una petición anterior a un dominio de tenant;
            // el contexto central debe partir siempre limpio.
            if (tenancy()->initialized) {
                tenancy()->end();
            }

            // Contexto central: sin team activo para spatie (research.md D2). El super admin no
            // tiene roles spatie (pasa por Gate::before), pero dejar el team seteado de una
            // petición previa (proceso PHP reutilizado) filtraría roles de un tenant ajeno.
            app(PermissionRegistrar::class)->setPermissionsTeamId(null);

            $response = $next($request);
            $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');

            return $response;
        }

        $domain = Domain::where('domain', $host)->first();

        if (! $domain) {
            abort(404);
        }

        $tenant = $domain->tenant;

        if (! $tenant || ! $tenant->activo) {
            if (Auth::check()) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }

            return redirect()->route('login');
        }

        tenancy()->initialize($tenant);

        // Fija el team de spatie al tenant activo: todo check de permisos/roles de esta petición
        // queda particionado por tenant (Principio I, research.md D2).
        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->getTenantKey());

        $response = $next($request);

        // Evita que el navegador muestre pantallas internas cacheadas (bfcache/back-forward)
        // después de un logout, tal como exige el escenario de aceptación de US2.
        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');

        return $response;
    }
}
