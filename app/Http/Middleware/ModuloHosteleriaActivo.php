<?php

namespace App\Http\Middleware;

use App\Support\ConfigPos;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Segunda capa de control de acceso del módulo de hostelería (feature 038, research.md D6):
 * el **estado del módulo en el tenant**, independiente del permiso del usuario.
 *
 * Son cosas distintas y confundirlas produce agujeros: un usuario puede tener `ver-pos-sala` y el
 * tenant tener el módulo apagado — debe recibir 404/403 igualmente (FR-003). Resolverlo quitando
 * permisos al apagar el módulo sería frágil y dejaría rastro sucio en los roles del tenant.
 *
 * El filtro del menú (`CatalogoMenu`/`MenuTenant`) es solo UX: **el enforcement real es este**.
 *
 * Ojo: las rutas de `configuracion.pos.*` NO lo llevan a propósito. Si lo llevaran, apagar el
 * módulo dejaría al administrador sin forma de volver a encenderlo (contracts/rutas.md).
 */
class ModuloHosteleriaActivo
{
    private const MENSAJE = 'El módulo de hostelería del POS está desactivado. Actívalo en Configuración → POS.';

    public function handle(Request $request, Closure $next, ?string $capacidad = null): Response
    {
        $tenantId = tenant()?->getTenantKey();

        if ($tenantId === null || ! ConfigPos::hosteleriaActivo((int) $tenantId)) {
            return $this->cortar($request);
        }

        // Capacidad concreta dentro del módulo (`modulo.hosteleria:opciones`). Sin argumento basta
        // con el interruptor maestro.
        $activa = match ($capacidad) {
            null => true,
            'opciones' => ConfigPos::opcionesActivo((int) $tenantId),
            'cobro_dividido' => ConfigPos::cobroDivididoActivo((int) $tenantId),
            'suplemento_zona' => ConfigPos::suplementoZonaActivo((int) $tenantId),
            default => false,
        };

        return $activa ? $next($request) : $this->cortar($request);
    }

    private function cortar(Request $request): Response
    {
        // JSON: 403 con mensaje legible, para que el front pueda mostrarlo con `showToast`.
        // Navegación normal: 404, porque para ese tenant la pantalla sencillamente no existe.
        if ($request->expectsJson()) {
            return response()->json(['message' => self::MENSAJE], 403);
        }

        abort(404);
    }
}
