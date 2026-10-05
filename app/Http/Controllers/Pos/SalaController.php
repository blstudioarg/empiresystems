<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Models\PosCuenta;
use App\Models\PosMesa;
use App\Models\PosPrecuenta;
use App\Models\PosZona;
use App\Services\PrecuentaCuenta;
use App\Support\ConfigPos;
use App\Support\PosPlanoCeldas;
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
 * de fechas (si la hiciera, dependería del reloj de la tablet). Igual con `precuenta_pedida`
 * (feature 049): se deriva aquí por huella, con una sola consulta extra para todas las mesas.
 */
class SalaController extends Controller
{
    public function __construct(private readonly PrecuentaCuenta $precuentas) {}

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

        // Precuenta (feature 049). Última precuenta de cada cuenta abierta: UNA consulta para
        // todas, agrupada en memoria. Solo las cuentas que tienen alguna necesitan la huella, y
        // para ella hacen falta las opciones (una consulta más) y la mesa con su zona, que ya
        // están en memoria y se enganchan sin volver a la base.
        $ultimasPrecuentas = $cuentas->isEmpty() ? collect() : PosPrecuenta::query()
            ->whereIn('cuenta_id', $cuentas->pluck('id')->all())
            ->orderByDesc('id')
            ->get()
            ->unique('cuenta_id')
            ->keyBy('cuenta_id');

        $conPrecuenta = $cuentas->filter(fn (PosCuenta $c) => $ultimasPrecuentas->has($c->id));
        if ($conPrecuenta->isNotEmpty()) {
            $conPrecuenta->load('lineas.opciones');
            $zonasPorId = $zonas->keyBy('id');
            $mesasPorId = $mesas->keyBy('id');
            $conPrecuenta->each(function (PosCuenta $c) use ($mesasPorId, $zonasPorId) {
                $mesa = $mesasPorId->get($c->mesa_id);
                $mesa?->setRelation('zona', $zonasPorId->get($mesa->zona_id));
                $c->setRelation('mesa', $mesa);
            });
        }

        $ahora = now();

        $mesasPayload = $mesas->map(function (PosMesa $mesa) use ($cuentas, $ultimasPrecuentas, $ahora, $umbral, $suplementoActivo) {
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
                    'precuenta_pedida' => false,
                    'precuenta_hace_min' => null,
                    'abrir_url' => route('pos.create'),
                    'fila' => $mesa->fila,
                    'columna' => $mesa->columna,
                    'forma' => $mesa->forma,
                    'ancho_celdas' => (int) $mesa->ancho_celdas,
                    'alto_celdas' => (int) $mesa->alto_celdas,
                ];
            }

            $minutos = $cuenta->abierta_en ? (int) $cuenta->abierta_en->diffInMinutes($ahora) : 0;

            // Cuarto estado (feature 049): la mesa ya tiene la cuenta en la mano y espera para
            // pagar. Solo con precuenta VIGENTE: si el consumo cambió, ya no es la que tiene.
            $ultima = $ultimasPrecuentas->get($cuenta->id);
            $precuentaPedida = $this->precuentas->estado($cuenta, $ultima, $suplementoActivo) === PrecuentaCuenta::ESTADO_VIGENTE;

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
                // "Precuenta pedida" prevalece sobre "olvidada" (FR-018): la mesa no está
                // olvidada, está esperando para pagar.
                'olvidada' => ! $precuentaPedida && $minutos >= $umbral,
                'precuenta_pedida' => $precuentaPedida,
                'precuenta_hace_min' => $precuentaPedida ? (int) $ultima->emitida_en->diffInMinutes($ahora) : null,
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
                // Lienzo de la zona (feature 042). Viaja SIEMPRE, tenga el usuario permiso de
                // configuración o no (FR-012): el contorno de la sala es lo que dibujan las dos
                // vistas del plano, y el camarero necesita ver la misma sala que colocó el
                // encargado. No añade ninguna consulta: la zona ya estaba cargada.
                'columnas' => PosPlanoCeldas::columnasDe($zona),
                'filas' => PosPlanoCeldas::filasDe($zona),
                'celdas_inactivas' => $zona->celdasInactivas(),
            ])->values(),
            'mesas' => $mesasPayload,
            'umbral_olvidada_min' => $umbral,
        ];
    }
}
