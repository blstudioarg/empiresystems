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
            'logoPath' => tenant()->logo_path,
            'logoMiniPath' => tenant()->logo_mini_path,
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
                ])
                ->delete();

            $this->borrarLogo($tenant, 'logo_path');
            $this->borrarLogo($tenant, 'logo_mini_path');
            $tenant->update(['logo_path' => null, 'logo_mini_path' => null]);
        } else {
            foreach (['color_primario', 'color_secundario', 'color_topbar'] as $campo) {
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
