<?php

namespace App\Http\Controllers\Pos;

use App\Exceptions\PagoTicketDescuadradoException;
use App\Exceptions\TicketFueraDeTopeException;
use App\Http\Controllers\Controller;
use App\Http\Requests\CobrarCuentaPosRequest;
use App\Http\Requests\GuardarCuentaPosRequest;
use App\Models\PosCuenta;
use App\Models\PosCuentaLinea;
use App\Models\PosMesa;
use App\Models\PosOpcion;
use App\Services\CobradorCuenta;
use App\Services\TransferidorCuenta;
use App\Support\ConfigPos;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Cuentas abiertas del POS (feature 038).
 *
 * **Ningún modelo se resuelve por route binding implícito.** El binding implícito de Laravel no
 * pasa por el `TenantScope`, así que devolvería registros de otro tenant; cada método busca a
 * mano bajo el scope (regla transversal del proyecto).
 */
class CuentaController extends Controller
{
    public function __construct(
        private readonly CobradorCuenta $cobrador,
        private readonly TransferidorCuenta $transferidor,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'mesa_id' => ['nullable', 'integer'],
            'comensales' => ['nullable', 'integer', 'min:1', 'max:255'],
        ]);

        $mesa = $this->resolverMesa($datos['mesa_id'] ?? null);

        if ($mesa !== null) {
            $abierta = PosCuenta::query()
                ->where('mesa_id', $mesa->id)
                ->where('estado', PosCuenta::ESTADO_ABIERTA)
                ->first();

            if ($abierta !== null) {
                // No se abre una segunda cuenta sobre la misma mesa: se retoma la que hay. Es lo
                // que evita que dos camareros creen cuentas paralelas de la misma mesa.
                return response()->json($this->payload($abierta), 200);
            }
        }

        $cuenta = PosCuenta::create([
            'tenant_id' => tenant()->getTenantKey(),
            'mesa_id' => $mesa?->id,
            'comensales' => $datos['comensales'] ?? null,
            'estado' => PosCuenta::ESTADO_ABIERTA,
            'abierta_por' => $request->user()?->id,
            'abierta_en' => now(),
            'version' => 1,
        ]);

        return response()->json($this->payload($cuenta), 201);
    }

    public function show(string $cuenta): JsonResponse
    {
        return response()->json($this->payload($this->resolverCuenta($cuenta)));
    }

    public function update(GuardarCuentaPosRequest $request, string $cuenta): JsonResponse
    {
        $modelo = $this->resolverCuenta($cuenta);

        if (! $modelo->estaAbierta()) {
            return response()->json(['message' => 'Esta cuenta ya no está abierta.'], 422);
        }

        if ($conflicto = $this->conflictoDeVersion($modelo, (int) $request->validated('version'))) {
            return $conflicto;
        }

        $mesa = $this->resolverMesa($request->validated('mesa_id'));
        $datos = $request->validated();

        DB::transaction(function () use ($modelo, $mesa, $datos) {
            $modelo->fill([
                'mesa_id' => $mesa?->id,
                'comensales' => $datos['comensales'] ?? null,
                'notas' => $datos['notas'] ?? null,
                'cliente_id' => $datos['receptor']['cliente_id'] ?? null,
                'cliente_nif' => $datos['receptor']['cliente_nif'] ?? null,
                'cliente_nombre' => $datos['receptor']['cliente_nombre'] ?? null,
                'cliente_razon_social' => $datos['receptor']['cliente_razon_social'] ?? null,
                'cliente_direccion' => $datos['receptor']['cliente_direccion'] ?? null,
            ]);
            $modelo->version = (int) $modelo->version + 1;
            $modelo->save();

            $this->sincronizarLineas($modelo, $datos['lineas']);
        });

        return response()->json($this->payload($modelo->refresh()));
    }

    public function anular(Request $request, string $cuenta): JsonResponse
    {
        $modelo = $this->resolverCuenta($cuenta);

        if (! $modelo->estaAbierta()) {
            return response()->json(['message' => 'Esta cuenta ya no está abierta.'], 422);
        }

        $modelo->load('lineas');

        // Anular una cuenta parcialmente cobrada solo puede afectar a lo PENDIENTE: lo emitido es
        // un documento inmutable y solo se corrige por rectificativa (Principio II).
        $yaCobrado = $modelo->lineas->sum(fn (PosCuentaLinea $l) => (float) $l->cantidad_saldada) > 0;

        DB::transaction(function () use ($modelo) {
            $modelo->estado = PosCuenta::ESTADO_ANULADA;
            $modelo->cerrada_en = now();
            $modelo->version = (int) $modelo->version + 1;
            $modelo->save();
        });

        Log::info('pos.cuenta_anulada', [
            'cuenta_id' => $modelo->id,
            'usuario_id' => $request->user()?->id,
            'parcialmente_cobrada' => $yaCobrado,
        ]);

        return response()->json([
            'message' => $yaCobrado
                ? 'Cuenta anulada. Lo ya cobrado sigue emitido: si hay que devolverlo, emite una rectificativa.'
                : 'Cuenta anulada.',
            'cuenta_cerrada' => true,
        ]);
    }

    public function transferir(Request $request, string $cuenta): JsonResponse
    {
        $modelo = $this->resolverCuenta($cuenta);
        $datos = $request->validate(['mesa_id' => ['nullable', 'integer']]);

        $destino = $this->resolverMesa($datos['mesa_id'] ?? null);

        $this->transferidor->transferir($modelo, $destino);

        Log::info('pos.cuenta_transferida', [
            'cuenta_id' => $modelo->id,
            'mesa_destino_id' => $destino?->id,
            'usuario_id' => $request->user()?->id,
        ]);

        return response()->json([
            'message' => 'Cuenta transferida.',
            'cuenta' => $this->payload($modelo->refresh()),
        ]);
    }

    public function unir(Request $request, string $cuenta): JsonResponse
    {
        $origen = $this->resolverCuenta($cuenta);
        $datos = $request->validate(['cuenta_destino_id' => ['required', 'integer']]);

        $destino = $this->resolverCuenta((string) $datos['cuenta_destino_id']);

        $this->transferidor->unir($origen, $destino);

        Log::info('pos.cuentas_unidas', [
            'cuenta_origen_id' => $origen->id,
            'cuenta_destino_id' => $destino->id,
            'usuario_id' => $request->user()?->id,
        ]);

        return response()->json([
            'message' => 'Cuentas unidas.',
            'cuenta' => $this->payload($destino->refresh()),
        ]);
    }

    public function cobrar(CobrarCuentaPosRequest $request, string $cuenta): JsonResponse
    {
        $modelo = $this->resolverCuenta($cuenta);

        if ($conflicto = $this->conflictoDeVersion($modelo, (int) $request->validated('version'))) {
            return $conflicto;
        }

        try {
            $resultado = $this->cobrador->cobrar(
                cuenta: $modelo,
                seleccion: $request->seleccion(),
                pagos: $request->validated('pagos'),
                receptor: $request->validated('receptor'),
                usuarioId: $request->user()?->id,
            );
        } catch (TicketFueraDeTopeException|PagoTicketDescuadradoException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'id' => $resultado['factura']->id,
            'numero_completo' => $resultado['factura']->numero_completo,
            'cuenta_cerrada' => $resultado['cuenta_cerrada'],
            'pendiente' => number_format($resultado['pendiente'], 2, '.', ''),
        ], 201);
    }

    // ── Resolución y utilidades ──────────────────────────────────────────

    private function resolverCuenta(string $id): PosCuenta
    {
        // Resolución manual bajo TenantScope: `findOrFail` sobre el modelo (no binding implícito).
        return PosCuenta::query()->with('lineas.opciones', 'mesa.zona')->findOrFail($id);
    }

    private function resolverMesa(mixed $id): ?PosMesa
    {
        if ($id === null || $id === '') {
            return null;
        }

        $mesa = PosMesa::query()->find($id);

        if ($mesa === null) {
            // 422 y no 404: para el usuario es un dato inválido del formulario, y devolver 404
            // aquí filtraría que el id existe en otro tenant.
            throw ValidationException::withMessages([
                'mesa_id' => 'La mesa indicada no existe.',
            ]);
        }

        return $mesa;
    }

    /**
     * Bloqueo optimista (FR-024): si la versión que trae el cliente no es la de base de datos,
     * otro dispositivo tocó la cuenta. Se responde 409 con el estado actual y **nunca** se
     * reintenta en silencio, que sería exactamente pisar los cambios del otro camarero.
     */
    private function conflictoDeVersion(PosCuenta $cuenta, int $version): ?JsonResponse
    {
        if ($version === (int) $cuenta->version) {
            return null;
        }

        return response()->json([
            'message' => 'Otro dispositivo modificó esta cuenta mientras la tenías abierta. Se recargó con los datos actuales.',
            'cuenta' => $this->payload($cuenta),
        ], 409);
    }

    /**
     * Reescribe las líneas de la cuenta con lo que manda el cliente. Los precios NO se toman del
     * cliente: se releen del catálogo, y el suplemento de las opciones se suma en servidor
     * (Principio III — el cliente dice qué eligió, nunca cuánto cuesta).
     *
     * @param  list<array<string, mixed>>  $lineas
     */
    private function sincronizarLineas(PosCuenta $cuenta, array $lineas): void
    {
        $tenantId = (int) $cuenta->tenant_id;
        $opcionesActivas = ConfigPos::opcionesActivo($tenantId);

        $cuenta->load('lineas');
        $saldadasPorId = $cuenta->lineas->keyBy('id');

        $conservadas = [];

        foreach (array_values($lineas) as $orden => $datos) {
            $existente = isset($datos['id']) ? $saldadasPorId->get((int) $datos['id']) : null;

            $articulo = ! empty($datos['articulo_id'])
                ? \App\Models\Articulo::query()->find($datos['articulo_id'])
                : null;

            $opcionesElegidas = $opcionesActivas
                ? $this->resolverOpciones($articulo?->id, $datos['opciones'] ?? [])
                : [];

            if ($opcionesActivas && $articulo !== null) {
                $this->validarReglasDeGrupo($articulo, $opcionesElegidas);
            }

            $suplemento = round(array_sum(array_column($opcionesElegidas, 'precio')), 2);

            $atributos = [
                'tenant_id' => $tenantId,
                'articulo_id' => $articulo?->id,
                'concepto' => $articulo?->nombre ?? $datos['concepto'],
                'unidad' => $articulo?->unidad ?? ($datos['unidad'] ?? null),
                'cantidad' => (float) $datos['cantidad'],
                'precio_unitario' => $articulo ? (float) $articulo->precio : 0,
                'suplemento_opciones' => $suplemento,
                'tipo_impositivo' => $articulo ? (float) $articulo->tipo_impositivo : (float) $datos['tipo_impositivo'],
                'orden' => $orden,
            ];

            if ($existente !== null) {
                // Una línea con unidades ya cobradas no puede reducirse por debajo de lo saldado.
                $atributos['cantidad'] = max((float) $atributos['cantidad'], (float) $existente->cantidad_saldada);
                $existente->update($atributos);
                $linea = $existente;
                $linea->opciones()->delete();
            } else {
                $linea = $cuenta->lineas()->create($atributos);
            }

            foreach ($opcionesElegidas as $opcion) {
                $linea->opciones()->create([
                    'tenant_id' => $tenantId,
                    'opcion_id' => $opcion['opcion_id'],
                    'nombre' => $opcion['nombre'],
                    'precio' => $opcion['precio'],
                    'articulo_vinculado_id' => $opcion['articulo_vinculado_id'],
                ]);
            }

            $conservadas[] = $linea->id;
        }

        // Las líneas que el cliente ya no manda se borran, salvo que tengan unidades cobradas:
        // esas son el respaldo de un documento emitido y no pueden desaparecer.
        $cuenta->lineas()
            ->whereNotIn('id', $conservadas ?: [0])
            ->where('cantidad_saldada', '<=', 0)
            ->each(fn (PosCuentaLinea $linea) => $linea->delete());
    }

    /**
     * FR-042/SC-006: no se puede confirmar una línea sin cumplir los grupos obligatorios del
     * artículo. Se revalida aquí aunque el cliente ya valide en JS, porque el servidor es quien
     * decide (Principio III) — nunca confiar en que el front hizo bien su trabajo.
     *
     * @param  list<array{opcion_id: int}>  $opcionesElegidas
     */
    private function validarReglasDeGrupo(\App\Models\Articulo $articulo, array $opcionesElegidas): void
    {
        $grupos = $articulo->posGrupos()->where('pos_opcion_grupos.obligatorio', true)->get();

        if ($grupos->isEmpty()) {
            return;
        }

        $idsElegidos = array_column($opcionesElegidas, 'opcion_id');
        $opcionesPorGrupo = PosOpcion::query()
            ->whereIn('id', $idsElegidos)
            ->pluck('grupo_id', 'id');

        foreach ($grupos as $grupo) {
            $elegidasDelGrupo = collect($idsElegidos)->filter(fn ($id) => ($opcionesPorGrupo[$id] ?? null) === $grupo->id)->count();

            if ($elegidasDelGrupo < max(1, (int) $grupo->min_selecciones)) {
                throw ValidationException::withMessages([
                    'lineas' => "«{$articulo->nombre}» necesita elegir «{$grupo->nombre}» antes de comandarse.",
                ]);
            }
        }
    }

    /**
     * Resuelve las opciones elegidas para un artículo **con el precio del pivot**, que es el que
     * manda (FR-039). Una opción que no esté asignada a ese artículo se ignora.
     *
     * @param  list<array{opcion_id: int}>  $elegidas
     * @return list<array{opcion_id: int, nombre: string, precio: float, articulo_vinculado_id: ?int}>
     */
    private function resolverOpciones(?int $articuloId, array $elegidas): array
    {
        if ($articuloId === null || $elegidas === []) {
            return [];
        }

        $ids = array_map(fn ($o) => (int) $o['opcion_id'], $elegidas);

        $opciones = PosOpcion::query()
            ->whereIn('pos_opciones.id', $ids)
            ->whereHas('articulos', fn ($q) => $q->where('articulos.id', $articuloId))
            ->with(['articulos' => fn ($q) => $q->where('articulos.id', $articuloId)])
            ->get();

        return $opciones->map(fn (PosOpcion $opcion) => [
            'opcion_id' => $opcion->id,
            'nombre' => $opcion->nombre,
            'precio' => (float) ($opcion->articulos->first()?->pivot->precio ?? $opcion->precio_defecto),
            'articulo_vinculado_id' => $opcion->articulo_vinculado_id,
        ])->all();
    }

    /** @return array<string, mixed> */
    private function payload(PosCuenta $cuenta): array
    {
        $cuenta->loadMissing('lineas.opciones', 'mesa.zona');
        $tenantId = (int) $cuenta->tenant_id;

        return [
            'id' => $cuenta->id,
            'estado' => $cuenta->estado,
            'version' => (int) $cuenta->version,
            'mesa_id' => $cuenta->mesa_id,
            'mesa_nombre' => $cuenta->mesa?->nombre,
            'zona_nombre' => $cuenta->mesa?->zona?->nombre,
            'zona_suplemento' => ConfigPos::suplementoZonaActivo($tenantId)
                ? number_format((float) ($cuenta->mesa?->zona?->suplemento_porcentaje ?? 0), 2, '.', '')
                : '0.00',
            'comensales' => $cuenta->comensales,
            'notas' => $cuenta->notas,
            'receptor' => $cuenta->cliente_nif ? [
                'cliente_id' => $cuenta->cliente_id,
                'cliente_nif' => $cuenta->cliente_nif,
                'cliente_nombre' => $cuenta->cliente_nombre,
                'cliente_razon_social' => $cuenta->cliente_razon_social,
                'cliente_direccion' => $cuenta->cliente_direccion,
            ] : null,
            'pendiente' => number_format($cuenta->pendiente(), 2, '.', ''),
            'lineas' => $cuenta->lineas->map(fn (PosCuentaLinea $linea) => [
                'id' => $linea->id,
                'articulo_id' => $linea->articulo_id,
                'concepto' => $linea->concepto,
                'unidad' => $linea->unidad,
                'cantidad' => (float) $linea->cantidad,
                'cantidad_saldada' => (float) $linea->cantidad_saldada,
                'cantidad_pendiente' => $linea->cantidadPendiente(),
                'precio_unitario' => (float) $linea->precio_unitario,
                'suplemento_opciones' => (float) $linea->suplemento_opciones,
                'precio_efectivo' => $linea->precioEfectivo(),
                'tipo_impositivo' => (float) $linea->tipo_impositivo,
                'importe_pendiente' => $linea->brutoPendiente(),
                'opciones' => $linea->opciones->map(fn ($o) => [
                    'opcion_id' => $o->opcion_id,
                    'nombre' => $o->nombre,
                    'precio' => (float) $o->precio,
                ])->values(),
            ])->values(),
        ];
    }
}
