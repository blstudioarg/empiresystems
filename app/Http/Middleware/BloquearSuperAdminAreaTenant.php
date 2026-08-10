<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class BloquearSuperAdminAreaTenant
{
    private const MENSAJE = 'Esa sección pertenece al área de empresa. Como Super Admin solo tenés acceso al panel de gestión de tenants.';

    /**
     * Allowlist exhaustiva de nombres de ruta que el Super Admin conserva dentro del área de
     * tenant (contracts/http.md §1): perfil propio, cierre de sesión y el catálogo geográfico
     * compartido que usan los formularios de su propio panel.
     */
    private const ALLOWLIST = ['logout', 'localidades.index'];

    /**
     * Corta el acceso del Super Admin a cualquier pantalla/acción del área de tenant (FR-001,
     * FR-002): colgado del grupo de rutas, no de secciones sueltas, para que toda ruta futura
     * nazca bloqueada sin acordarse de esta regla (SC-002). No toca `Gate::before`
     * (AppServiceProvider.php) ni el bypass de permisos, que es deseado (research.md D1).
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->isSuperAdmin()) {
            return $next($request);
        }

        $nombreRuta = $request->route()?->getName();

        if ($nombreRuta !== null && (in_array($nombreRuta, self::ALLOWLIST, true) || str_starts_with($nombreRuta, 'profile.'))) {
            return $next($request);
        }

        Log::warning('super_admin.acceso_area_tenant_bloqueado', [
            'usuario_id' => $user->id,
            'usuario_email' => $user->email,
            'metodo' => $request->method(),
            'uri' => $request->fullUrl(),
            'ruta' => $nombreRuta,
            'ip' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 255),
        ]);

        if ($request->expectsJson()) {
            return response()->json(['message' => self::MENSAJE], 403);
        }

        return redirect()->route('super_admin.home')->with('warning', self::MENSAJE);
    }
}
