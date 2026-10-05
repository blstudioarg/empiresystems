<?php

namespace App\Http\Controllers\Configuracion;

use App\Http\Controllers\Controller;
use App\Models\PosCuenta;
use App\Support\ConfigPos;
use App\Support\MenuTenant;
use App\Traduccion\CargadorTraducciones;
use App\Traduccion\MemoriaTraducciones;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Pestaña Configuración → POS (feature 038, US1).
 *
 * Estas rutas llevan `can:ver-configuracion` y **no** el middleware de módulo activo: si lo
 * llevaran, apagar el módulo dejaría al administrador sin forma de volver a encenderlo.
 */
class PosConfiguracionController extends Controller
{
    public function update(Request $request): RedirectResponse|JsonResponse
    {
        $tenantId = (int) tenant()->getTenantKey();

        $datos = $request->validate([
            // Idioma del POS (feature 050): bloque propio de la pestaña, que se guarda solo; sin el
            // campo, el idioma no cambia. Por eso los flags del módulo pasan a ser obligatorios
            // solo cuando no se está guardando el idioma.
            'idioma' => ['sometimes', 'string', 'in:'.implode(',', array_keys((array) config('traduccion.idiomas')))],
            'hosteleria_activo' => ['required_without:idioma', 'boolean'],
            'opciones_activo' => ['required_without:idioma', 'boolean'],
            'cobro_dividido_activo' => ['required_without:idioma', 'boolean'],
            'suplemento_zona_activo' => ['required_without:idioma', 'boolean'],
            'mesa_olvidada_min' => ['required_without:idioma', 'integer', 'min:1', 'max:1440'],
            // Caja (feature 048): `sometimes` para no romper a quien guarda solo la hostelería.
            'caja_umbral_descuadre' => ['sometimes', 'numeric', 'min:0', 'max:9999.99'],
        ]);

        // FR-006: apagar el módulo con cuentas abiertas dejaría consumo real inaccesible y mesas
        // ocupadas sin forma de cobrarlas. Se bloquea diciendo cuántas hay, no en abstracto.
        if (array_key_exists('hosteleria_activo', $datos) && ! $datos['hosteleria_activo'] && ConfigPos::hosteleriaActivo($tenantId)) {
            $abiertas = PosCuenta::query()->where('estado', PosCuenta::ESTADO_ABIERTA)->count();

            if ($abiertas > 0) {
                $mensaje = $abiertas === 1
                    ? 'Hay 1 cuenta abierta. Ciérrala o anúlala antes de desactivar el módulo.'
                    : "Hay {$abiertas} cuentas abiertas. Ciérralas o anúlalas antes de desactivar el módulo.";

                if ($request->wantsJson()) {
                    return response()->json(['message' => $mensaje, 'cuentas_abiertas' => $abiertas], 422);
                }

                return redirect()->route('configuracion.show')->with('error', $mensaje);
            }
        }

        // Apagar una capacidad NUNCA borra datos (FR-007): zonas, mesas, grupos y opciones se
        // conservan intactos y reaparecen al reactivarla.
        ConfigPos::guardar($tenantId, $datos);

        MenuTenant::invalidarCache($tenantId);

        $mensaje = 'Configuración del POS guardada correctamente.';

        if ($request->wantsJson()) {
            $config = ConfigPos::todo($tenantId);

            // Con el POS traducido, la pantalla avisa si quedan textos sin traducir todavía.
            $pendientes = CargadorTraducciones::esIdiomaTraducido($config['idioma'])
                ? app(MemoriaTraducciones::class)->contarPendientes($config['idioma'])
                : 0;

            return response()->json([
                'message' => $mensaje,
                'config' => $config,
                'idioma' => $config['idioma'],
                'traducciones_pendientes' => $pendientes,
            ]);
        }

        return redirect()->route('configuracion.show')->with('success', $mensaje);
    }
}
