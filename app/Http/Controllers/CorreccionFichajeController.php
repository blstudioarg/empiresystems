<?php

namespace App\Http\Controllers;

use App\Enums\TipoEventoFichaje;
use App\Http\Requests\CorregirFichajeRequest;
use App\Models\Fichaje;
use App\Services\RegistroFichajes;
use App\Support\ConfigTenant;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class CorreccionFichajeController extends Controller
{
    public function __construct(private readonly RegistroFichajes $registroFichajes) {}

    public function store(CorregirFichajeRequest $request, string $fichaje): RedirectResponse|JsonResponse
    {
        $tenantId = tenant()->getTenantKey();

        $original = Fichaje::where('tenant_id', $tenantId)->findOrFail($fichaje);

        // El formulario prellena y muestra `ocurrido_at` en la hora local del tenant, así que el
        // valor enviado viene en esa zona: se interpreta ahí y se convierte a UTC (app.timezone)
        // antes de guardar, para mantener la fuente de verdad en UTC como el resto de fichajes.
        $ocurridoAt = Carbon::parse($request->string('ocurrido_at')->toString(), ConfigTenant::zonaHoraria($tenantId))
            ->setTimezone(config('app.timezone'));

        $this->registroFichajes->corregir(
            $original,
            TipoEventoFichaje::from($request->string('tipo')->toString()),
            $ocurridoAt,
            $request->string('motivo')->toString(),
            $request->user(),
        );

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Corrección registrada correctamente.']);
        }

        return redirect()->back()->with('success', 'Corrección registrada correctamente.');
    }
}
