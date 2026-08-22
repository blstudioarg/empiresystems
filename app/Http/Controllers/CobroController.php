<?php

namespace App\Http\Controllers;

use App\Http\Requests\FiltroCobrosRequest;
use App\Models\Cliente;
use App\Models\Serie;
use App\Support\ConsultaCobros;
use App\Support\RangoFechas;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Módulo de Cobros (feature 043): pantalla de consulta y gestión de cobro de facturas.
 * Cero lógica de negocio nueva (FR-004) — todo el cálculo vive en App\Support\ConsultaCobros
 * (espejo de los métodos de App\Models\Factura) y el registro/anulación de pagos sigue pasando
 * por las rutas y servicios ya existentes (App\Services\RegistroPagos, App\Http\Controllers\PagoController).
 */
class CobroController extends Controller
{
    public function index(FiltroCobrosRequest $request): View|JsonResponse
    {
        if (! $request->has('draw')) {
            return view('cobros.index', [
                'clientes' => Cliente::where('tenant_id', $request->user()->tenant_id)
                    ->orderBy('nombre')->get(['id', 'nombre', 'razon_social']),
                'series' => Serie::where('tenant_id', $request->user()->tenant_id)
                    ->orderBy('codigo')->get(['id', 'codigo']),
            ]);
        }

        $tenantId = $request->user()->tenant_id;
        $filtros = $this->filtros($request);

        // recordsTotal: antes de aplicar filtros/búsqueda del DataTable (patrón LogActividadController).
        $recordsTotal = ConsultaCobros::query($tenantId)->count();

        $query = ConsultaCobros::query($tenantId);
        ConsultaCobros::aplicarFiltros($query, $filtros);

        $termino = trim((string) $request->input('search.value', ''));
        ConsultaCobros::aplicarBusqueda($query, $termino);

        $recordsFiltered = (clone $query)->get()->count();

        $columnaOrden = $request->input('order.0.column');
        $direccion = (string) $request->input('order.0.dir', 'asc');
        $dataOrden = $columnaOrden !== null ? $request->input("columns.{$columnaOrden}.data") : null;

        ConsultaCobros::aplicarOrden($query, $dataOrden, $direccion);

        $start = max(0, (int) $request->input('start', 0));
        $length = max(1, (int) $request->input('length', 10));

        $filas = $query->skip($start)->take($length)->get();

        $puedeVerFacturas = (bool) $request->user()->can('ver-facturas');

        return response()->json([
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $filas->map(fn ($fila) => $this->filaJson($fila, $puedeVerFacturas))->values(),
        ]);
    }

    public function resumen(FiltroCobrosRequest $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $filtros = $this->filtros($request);
        $rango = RangoFechas::desdePeticion($filtros);

        // Pendiente/vencido/nº de facturas: instantáneas a hoy, ignoran el rango (FR-009, D4).
        $filasElegibles = ConsultaCobros::query($tenantId)->get();

        $pendienteTotal = $filasElegibles
            ->filter(fn ($f) => (float) $f->saldo_pendiente > 0)
            ->sum(fn ($f) => (float) $f->saldo_pendiente);

        $facturasPendientes = $filasElegibles->filter(fn ($f) => (float) $f->saldo_pendiente > 0)->count();

        $vencidoTotal = $filasElegibles
            ->filter(fn ($f) => (int) $f->vencida === 1)
            ->sum(fn ($f) => (float) $f->saldo_pendiente);

        // Cobrado en el periodo: pagos vigentes con fecha dentro del rango, sobre facturas
        // elegibles del tenant (FR-009, criterio evento — D4).
        $idsElegibles = $filasElegibles->pluck('id');

        $cobradoPeriodo = DB::table('pagos')
            ->where('pagos.tenant_id', $tenantId)
            ->whereIn('pagos.factura_id', $idsElegibles)
            ->whereNull('pagos.anulado_at')
            ->whereDate('pagos.fecha', '>=', $rango->desde->toDateString())
            ->whereDate('pagos.fecha', '<=', $rango->hasta->toDateString())
            ->sum('pagos.importe');

        return response()->json([
            'pendiente_total' => number_format((float) $pendienteTotal, 2, '.', ''),
            'cobrado_periodo' => number_format((float) $cobradoPeriodo, 2, '.', ''),
            'vencido_total' => number_format((float) $vencidoTotal, 2, '.', ''),
            'facturas_pendientes' => $facturasPendientes,
            'periodo' => [
                'preset' => $rango->preset->value,
                'desde' => $rango->desde->toDateString(),
                'hasta' => $rango->hasta->toDateString(),
                'etiqueta' => $rango->etiqueta(),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function filtros(FiltroCobrosRequest $request): array
    {
        $validado = $request->validated();
        $validado['solo_vencidas'] = $request->boolean('solo_vencidas');

        return $validado;
    }

    /**
     * @return array<string, mixed>
     */
    private function filaJson(object $fila, bool $puedeVerFacturas): array
    {
        // $fila->estado puede llegar como el enum EstadoFactura (fila hidratada como modelo
        // Factura) o como string plano según el driver/consulta; se admiten ambas formas.
        $estadoValor = $fila->estado instanceof \App\Enums\EstadoFactura ? $fila->estado->value : $fila->estado;
        $esRectificada = $estadoValor === 'rectificada';
        $saldoPendiente = (float) $fila->saldo_pendiente;

        $cliente = $fila->cliente_razon_social ?: $fila->cliente_nombre;

        // Igual que fecha_expedicion/fecha_vencimiento: la fila hidrata como modelo Factura, cuyo
        // cast 'date' las devuelve como Carbon — hay que formatearlas, nunca serializarlas crudas
        // (guía "Nunca imprimir directo un campo decimal/fecha sin formatear").
        $fechaExpedicion = $fila->fecha_expedicion instanceof \Carbon\CarbonInterface
            ? $fila->fecha_expedicion->toDateString()
            : $fila->fecha_expedicion;
        $fechaVencimiento = $fila->fecha_vencimiento instanceof \Carbon\CarbonInterface
            ? $fila->fecha_vencimiento->toDateString()
            : $fila->fecha_vencimiento;

        return [
            'id' => $fila->id,
            'identificador' => $fila->numero_completo,
            'cliente' => $cliente,
            'serie' => $fila->serie_codigo,
            'fecha_expedicion' => $fechaExpedicion,
            'fecha_vencimiento' => $fechaVencimiento,
            'total_cobrable' => number_format((float) $fila->total_cobrable, 2, '.', ''),
            'monto_cobrado' => number_format((float) $fila->cobrado, 2, '.', ''),
            'saldo_pendiente' => number_format($saldoPendiente, 2, '.', ''),
            'estado_cobro' => $fila->estado_cobro,
            'vencida' => (bool) $fila->vencida,
            'dias_retraso' => $fila->dias_retraso !== null ? (int) $fila->dias_retraso : null,
            'es_rectificada' => $esRectificada,
            'total_nominal' => number_format((float) $fila->total, 2, '.', ''),
            'modalidad_rectificacion' => $fila->modalidad_rectificacion,
            'pago_url' => $saldoPendiente > 0 ? route('facturas.pagos.store', $fila->id) : null,
            'cobros_url' => route('facturas.pagos.index', $fila->id),
            'pdf_url' => $puedeVerFacturas ? route('facturas.pdf', $fila->id) : null,
        ];
    }
}
