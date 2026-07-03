<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateAparienciaRequest;
use App\Models\Configuracion;
use App\Support\AparienciaTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class ConfiguracionController extends Controller
{
    public function show(): View
    {
        $tenantId = tenant()->getTenantKey();

        return view('configuracion.index', [
            'colores' => AparienciaTenant::coloresEfectivos($tenantId),
            'extras' => AparienciaTenant::extrasEfectivos($tenantId),
            'logoPath' => tenant()->logo_path,
            'logoMiniPath' => tenant()->logo_mini_path,
            'loginLogoPath' => tenant()->login_logo_path,
            'loginImagenPath' => tenant()->login_imagen_path,
            'logoFacturacionPath' => tenant()->logo_facturacion_path,
            'faviconPath' => tenant()->favicon_path,
        ]);
    }

    public function update(UpdateAparienciaRequest $request): RedirectResponse|JsonResponse
    {
        $tenant = tenant();
        $tenantId = $tenant->getTenantKey();
        $datos = $request->validated();

        if ($datos['restablecer'] ?? false) {
            Configuracion::query()
                ->where('tenant_id', $tenantId)
                ->where('grupo', 'apariencia')
                ->whereIn('clave', [
                    'apariencia.color_primario',
                    'apariencia.color_secundario',
                    'apariencia.color_topbar',
                    'apariencia.facebook_url',
                    'apariencia.instagram_url',
                    'apariencia.titulo_login',
                ])
                ->delete();

            $this->borrarLogo($tenant, 'logo_path');
            $this->borrarLogo($tenant, 'logo_mini_path');
            $this->borrarLogo($tenant, 'login_logo_path');
            $this->borrarLogo($tenant, 'login_imagen_path');
            $this->borrarLogo($tenant, 'logo_facturacion_path');
            $this->borrarLogo($tenant, 'favicon_path');
            $tenant->update([
                'logo_path' => null,
                'logo_mini_path' => null,
                'login_logo_path' => null,
                'login_imagen_path' => null,
                'logo_facturacion_path' => null,
                'favicon_path' => null,
            ]);
        } else {
            foreach (['color_primario', 'color_secundario', 'color_topbar', 'facebook_url', 'instagram_url', 'titulo_login'] as $campo) {
                if (! empty($datos[$campo])) {
                    Configuracion::query()->updateOrCreate(
                        ['tenant_id' => $tenantId, 'clave' => "apariencia.{$campo}"],
                        ['valor' => $datos[$campo], 'tipo' => 'string', 'grupo' => 'apariencia']
                    );
                }
            }

            if ($request->hasFile('logo')) {
                $this->borrarLogo($tenant, 'logo_path');

                $ruta = $request->file('logo')->store("logos/{$tenantId}", 'public');
                $tenant->update(['logo_path' => $ruta]);
            }

            if ($request->hasFile('logo_mini')) {
                $this->borrarLogo($tenant, 'logo_mini_path');

                $ruta = $request->file('logo_mini')->store("logos/{$tenantId}", 'public');
                $tenant->update(['logo_mini_path' => $ruta]);
            }

            if ($request->hasFile('login_logo')) {
                $this->borrarLogo($tenant, 'login_logo_path');

                $ruta = $request->file('login_logo')->store("logos/{$tenantId}", 'public');
                $tenant->update(['login_logo_path' => $ruta]);
            }

            if ($request->hasFile('login_imagen')) {
                $this->borrarLogo($tenant, 'login_imagen_path');

                $ruta = $request->file('login_imagen')->store("logos/{$tenantId}", 'public');
                $tenant->update(['login_imagen_path' => $ruta]);
            }

            if ($request->hasFile('logo_facturacion')) {
                $this->borrarLogo($tenant, 'logo_facturacion_path');

                $ruta = $request->file('logo_facturacion')->store("logos/{$tenantId}", 'public');
                $tenant->update(['logo_facturacion_path' => $ruta]);
            }

            if ($request->hasFile('favicon')) {
                $this->borrarLogo($tenant, 'favicon_path');

                $ruta = $request->file('favicon')->store("logos/{$tenantId}", 'public');
                $tenant->update(['favicon_path' => $ruta]);
            }
        }

        AparienciaTenant::invalidarCache($tenantId);

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Apariencia guardada correctamente.']);
        }

        return redirect()->route('configuracion.show')->with('success', 'Apariencia guardada correctamente.');
    }

    private function borrarLogo($tenant, string $campo): void
    {
        if ($tenant->{$campo}) {
            Storage::disk('public')->delete($tenant->{$campo});
        }
    }
}
