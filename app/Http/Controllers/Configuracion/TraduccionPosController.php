<?php

namespace App\Http\Controllers\Configuracion;

use App\Http\Controllers\Controller;
use App\Http\Requests\CorregirTraduccionRequest;
use App\Models\Traduccion;
use App\Models\TraduccionCorreccion;
use App\Support\ConfigPos;
use App\Traduccion\CargadorTraducciones;
use Illuminate\Http\JsonResponse;

/**
 * Configuración → POS → «Traducciones del POS» (feature 050, US4, contracts/traducciones.md).
 *
 * Lista las traducciones del ámbito POS en el idioma del tenant y permite corregirlas. La
 * corrección es **del tenant** (Principio I, FR-018): se guarda en `traduccion_correcciones` con
 * `BelongsToTenant`, y la traducción automática (tabla central) nunca se toca desde aquí, así que
 * la sincronización no puede pisar una corrección (FR-017).
 *
 * Rutas con `can:ver-configuracion` y sin `modulo.hosteleria` (el idioma aplica al POS sin
 * hostelería, FR-002). Los modelos se resuelven a mano, nunca por binding implícito.
 */
class TraduccionPosController extends Controller
{
    private const AMBITO = 'pos';

    public function index(): JsonResponse
    {
        $idioma = $this->idioma();

        if (! CargadorTraducciones::esIdiomaTraducido($idioma)) {
            return response()->json(['idioma' => $idioma, 'data' => []]);
        }

        $correcciones = TraduccionCorreccion::query()
            ->with('corregidaPor:id,name')
            ->where('idioma', $idioma)
            ->get()
            ->keyBy('hash');

        $filas = Traduccion::query()
            ->where('idioma', $idioma)
            ->where('ambito', self::AMBITO)
            ->orderBy('texto')
            ->get()
            ->map(fn (Traduccion $t) => $this->fila($t, $correcciones->get($t->hash)))
            ->values();

        return response()->json(['idioma' => $idioma, 'data' => $filas]);
    }

    public function update(CorregirTraduccionRequest $request, string $hash): JsonResponse
    {
        $original = $request->traduccionCorregida() ?? abort(404);

        $correccion = TraduccionCorreccion::query()->updateOrCreate(
            ['idioma' => $original->idioma, 'hash' => $original->hash],
            [
                'texto' => $original->texto,
                'traduccion' => $request->traduccionSaneada($original),
                'corregida_por' => $request->user()->id,
            ],
        );

        return response()->json($this->fila($original, $correccion->load('corregidaPor:id,name')));
    }

    public function destroy(string $hash): JsonResponse
    {
        $idioma = $this->idioma();

        $correccion = TraduccionCorreccion::query()
            ->where('idioma', $idioma)
            ->where('hash', $hash)
            ->firstOrFail();

        $correccion->delete();

        $original = Traduccion::query()->where('idioma', $idioma)->where('hash', $hash)->firstOrFail();

        return response()->json($this->fila($original, null));
    }

    private function idioma(): string
    {
        return ConfigPos::idioma((int) tenant()->getTenantKey());
    }

    /** @return array<string, mixed> */
    private function fila(Traduccion $traduccion, ?TraduccionCorreccion $correccion): array
    {
        $origen = match (true) {
            $correccion !== null => 'corregida',
            $traduccion->estado === Traduccion::ESTADO_TRADUCIDA => 'automatica',
            default => 'pendiente',
        };

        return [
            'hash' => $traduccion->hash,
            'texto' => $traduccion->texto,
            'traduccion' => $correccion?->traduccion ?? ($origen === 'automatica' ? $traduccion->traduccion : null),
            'automatica' => $traduccion->estado === Traduccion::ESTADO_TRADUCIDA ? $traduccion->traduccion : null,
            'origen' => $origen,
            'estado' => $traduccion->estado,
            'es_html' => (bool) $traduccion->es_html,
            'corregida_por' => $correccion?->corregidaPor?->name,
            'corregida_en' => $correccion?->updated_at?->enZonaTenant()->format('d/m/Y H:i'),
        ];
    }
}
