<?php

namespace App\Http\Controllers\Configuracion;

use App\Http\Controllers\Controller;
use App\Models\PosCuenta;
use App\Support\ConfigPos;
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
            'hosteleria_activo' => ['required', 'boolean'],
            'opciones_activo' => ['required', 'boolean'],
            'cobro_dividido_activo' => ['required', 'boolean'],
            'suplemento_zona_activo' => ['required', 'boolean'],
            'mesa_olvidada_min' => ['required', 'integer', 'min:1', 'max:1440'],
        ]);

        // FR-006: apagar el módulo con cuentas abiertas dejaría consumo real inaccesible y mesas
        // ocupadas sin forma de cobrarlas. Se bloquea diciendo cuántas hay, no en abstracto.
        if (! $datos['hosteleria_activo'] && ConfigPos::hosteleriaActivo($tenantId)) {
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

        \App\Support\MenuTenant::invalidarCache($tenantId);

        $mensaje = 'Configuración del POS guardada correctamente.';

        if ($request->wantsJson()) {
            return response()->json(['message' => $mensaje, 'config' => ConfigPos::todo($tenantId)]);
        }

        return redirect()->route('configuracion.show')->with('success', $mensaje);
    }
}
