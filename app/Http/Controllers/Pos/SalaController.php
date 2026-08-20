<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Models\PosCuenta;
use App\Models\PosMesa;
use App\Models\PosZona;
use App\Support\ConfigPos;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Estado de la sala (feature 038).
 *
 * SC-001 exige pintar la sala en menos de 2 s con 20 cuentas abiertas, así que **el estado de
 * todas las mesas se resuelve sin N+1**: una consulta de zonas, una de mesas y una de cuentas
 * abiertas con sus líneas; el cruce se hace en memoria. Nada de una consulta por mesa.
 *
 * El servidor decide `olvidada` comparando con el umbral configurado: la vista no hace aritmética
 * de fechas (si la hiciera, dependería del reloj de la tablet).
 */
class SalaController extends Controller
{
    public function index(Request $request): View|JsonResponse
    {
        if (! $request->wantsJson()) {
            return view('pos.sala', [
                'umbralOlvidadaMin' => ConfigPos::mesaOlvidadaMin((int) tenant()->getTenantKey()),
            ]);
        }

        return response()->json($this->estado());
    }

    /** @return array<string, mixed> */
    private function estado(): array
    {
        $tenantId = (int) tenant()->getTenantKey();
        $umbral = ConfigPos::mesaOlvidadaMin($tenantId);
        $suplementoActivo = ConfigPos::suplementoZonaActivo($tenantId);

        $zonas = PosZona::query()->orderBy('orden')->orderBy('nombre')->get();
        $mesas = PosMesa::query()->orderBy('orden')->orderBy('nombre')->get();

        // Todas las cuentas abiertas del tenant de una vez, con sus líneas: el pendiente de cada
        // mesa sale de aquí sin volver a la base de datos.
        $cuentas = PosCuenta::query()
            ->where('estado', PosCuenta::ESTADO_ABIERTA)
            ->whereNotNull('mesa_id')
            ->with('lineas')
            ->get()
            ->keyBy('mesa_id');

        $ahora = now();

        $mesasPayload = $mesas->map(function (PosMesa $mesa) use ($cuentas, $ahora, $umbral) {
            $cuenta = $cuentas->get($mesa->id);

            if ($cuenta === null) {
                return [
                    'id' => $mesa->id,
                    'nombre' => $mesa->nombre,
                    'zona_id' => $mesa->zona_id,
                    'estado' => 'libre',
                    'cuenta_id' => null,
                    'pendiente' => '0.00',
                    'abierta_hace_min' => null,
                    'olvidada' => false,
                    'abrir_url' => route('pos.create'),
                    'fila' => $mesa->fila,
                    'columna' => $mesa->columna,
                    'forma' => $mesa->forma,
                    'ancho_celdas' => (int) $mesa->ancho_celdas,
                    'alto_celdas' => (int) $mesa->alto_celdas,
                ];
            }

            $minutos = $cuenta->abierta_en ? (int) $cuenta->abierta_en->diffInMinutes($ahora) : 0;

            return [
                'id' => $mesa->id,
                'nombre' => $mesa->nombre,
                'zona_id' => $mesa->zona_id,
                'estado' => 'ocupada',
                'cuenta_id' => $cuenta->id,
                // Importe POR COBRAR, no el consumido (FR-028): con cobros parciales de por medio
                // son cosas distintas y la mesa tiene que mostrar lo que falta.
                'pendiente' => number_format($cuenta->pendiente(), 2, '.', ''),
                'abierta_hace_min' => $minutos,
                'olvidada' => $minutos >= $umbral,
                'abrir_url' => route('pos.create', ['cuenta' => $cuenta->id]),
                'fila' => $mesa->fila,
                'columna' => $mesa->columna,
                'forma' => $mesa->forma,
                'ancho_celdas' => (int) $mesa->ancho_celdas,
                'alto_celdas' => (int) $mesa->alto_celdas,
            ];
        })->values();

        return [
            'zonas' => $zonas->map(fn (PosZona $zona) => [
                'id' => $zona->id,
                'nombre' => $zona->nombre,
                'suplemento' => $suplementoActivo ? number_format((float) $zona->suplemento_porcentaje, 2, '.', '') : '0.00',
                'total_mesas' => $mesas->where('zona_id', $zona->id)->count(),
                'version' => (int) $zona->version,
            ])->values(),
            'mesas' => $mesasPayload,
            'umbral_olvidada_min' => $umbral,
        ];
    }
}
