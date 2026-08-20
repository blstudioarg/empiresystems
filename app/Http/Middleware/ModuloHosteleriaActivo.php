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
 * tenant tener el módulo apagado — debe recibir el cartel de "módulo desactivado" igualmente (FR-003). Resolverlo quitando
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

    /** Mensajes por capacidad, para que el cartel diga exactamente qué hay que encender. */
    private const MENSAJE_CAPACIDAD = [
        'opciones' => 'Las opciones de artículo forman parte del módulo de hostelería del POS y ahora mismo están desactivadas. Actívalas en Configuración → POS.',
        'cobro_dividido' => 'El cobro dividido forma parte del módulo de hostelería del POS y ahora mismo está desactivado. Actívalo en Configuración → POS.',
        'suplemento_zona' => 'El suplemento por zona forma parte del módulo de hostelería del POS y ahora mismo está desactivado. Actívalo en Configuración → POS.',
    ];

    public function handle(Request $request, Closure $next, ?string $capacidad = null): Response
    {
        $tenantId = tenant()?->getTenantKey();

        if ($tenantId === null || ! ConfigPos::hosteleriaActivo((int) $tenantId)) {
            return $this->cortar($request, null);
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

        return $activa ? $next($request) : $this->cortar($request, $capacidad);
    }

    private function cortar(Request $request, ?string $capacidad): Response
    {
        $mensaje = self::MENSAJE_CAPACIDAD[$capacidad] ?? self::MENSAJE;

        // JSON: 403 con mensaje legible, para que el front pueda mostrarlo con `showToast`.
        if ($request->expectsJson()) {
            return response()->json(['message' => $mensaje], 403);
        }

        // Navegación normal: 403 con un cartel explicando que hay que activar el módulo. Antes
        // era un 404 seco, que parecía una pantalla rota o un despliegue incompleto en vez de un
        // interruptor apagado — el usuario no tenía forma de saber qué hacer.
        return response()->view('pos.modulo-inactivo', ['mensaje' => $mensaje], 403);
    }
}
