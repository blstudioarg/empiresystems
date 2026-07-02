<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class SetTenantContext
{
    /**
     * Inicializa el contexto de tenancy a partir del usuario autenticado.
     *
     * - Usuario con tenant activo -> inicializa tenancy con ese tenant.
     * - Super admin (tenant_id null) -> no inicializa, contexto central.
     * - Usuario cuyo tenant fue desactivado a mitad de sesión -> logout forzado.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if ($user && $user->tenant_id) {
            // Consulta fresca: no usar la relación cacheada en $user->tenant, que puede haber
            // quedado stale si el guard reutilizó la misma instancia de otro request (p. ej.
            // justo después de Auth::attempt() en el login).
            $tenant = $user->tenant()->first();

            if (! $tenant || ! $tenant->activo) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->route('login');
            }

            tenancy()->initialize($tenant);
        }

        $response = $next($request);

        // Evita que el navegador muestre pantallas internas cacheadas (bfcache/back-forward)
        // después de un logout, tal como exige el escenario de aceptación de US2.
        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');

        return $response;
    }
}
