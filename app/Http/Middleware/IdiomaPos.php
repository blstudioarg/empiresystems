<?php

namespace App\Http\Middleware;

use App\Support\ConfigPos;
use App\Traduccion\CargadorTraducciones;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Idioma del POS (feature 050, research D3): en las rutas del POS fija el locale del request al
 * idioma elegido por el tenant, de modo que todo lo que pase por `__()` (vistas, guías de ayuda,
 * mensajes del servidor) sale traducido sin pasar el idioma a mano.
 *
 * Solo se aplica a las rutas del POS: fuera de ellas la aplicación sigue en español (FR-008). Con
 * el POS en español no toca nada.
 */
class IdiomaPos
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenantId = tenant()?->getTenantKey();

        if ($tenantId !== null) {
            $idioma = ConfigPos::idioma((int) $tenantId);

            if (CargadorTraducciones::esIdiomaTraducido($idioma)) {
                app()->setLocale($idioma);

                // Lo cargado por el traductor es del proceso: en un servidor real cada request
                // arranca de cero, pero en un proceso que atiende varios (tests, Octane) una
                // corrección recién guardada no se vería. Recargar es una consulta por tabla.
                app('translator')->setLoaded([]);
            }
        }

        return $next($request);
    }
}
