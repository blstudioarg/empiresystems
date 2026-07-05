<?php

namespace App\Http\Controllers;

use App\Enums\AccionLogActividad;
use App\Enums\EntidadLogActividad;
use App\Enums\EstadoFactura;
use App\Enums\FormaPago;
use App\Enums\TipoFactura;
use App\Enums\TipoRectificacion;
use App\Exceptions\EmailNoConfiguradoException;
use App\Exceptions\FacturaNoEmitibleException;
use App\Exceptions\FacturaNoRectificableException;
use App\Http\Requests\StoreFacturaRequest;
use App\Http\Requests\StoreRectificativaRequest;
use App\Http\Requests\UpdateFacturaRequest;
use App\Mail\FacturaMail;
use App\Models\Cliente;
use App\Models\CuentaBancaria;
use App\Models\Factura;
use App\Models\FacturaEvento;
use App\Models\Serie;
use App\Services\CalculadoraFactura;
use App\Services\EmisorFacturas;
use App\Services\GeneradorRectificativa;
use App\Services\RegistradorActividad;
use App\Services\TenantMailer;
use App\Support\EmailTenant;
use App\Support\TiposImpositivos;
use App\Support\VencimientoFactura;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class FacturaController extends Controller
{
    public function __construct(
        private readonly CalculadoraFactura $calculadora,
        private readonly EmisorFacturas $emisor,
        private readonly GeneradorRectificativa $generadorRectificativa,
        private readonly RegistradorActividad $registradorActividad,
    ) {}

    public function index(Request $request): View|JsonResponse
    {
        if ($request->wantsJson()) {
            // Excluye las simplificadas: viven en su propio módulo POS (pos.index).
            $facturas = Factura::with(['cliente', 'eventos'])
                ->where('tipo', '!=', TipoFactura::Simplificada->value)
                ->orderByDesc('fecha_expedicion')
                ->get();

            return response()->json([
                'data' => $facturas->map(function (Factura $factura) {
                    $esBorrador = $factura->estado === EstadoFactura::Borrador;
                    $esAnulada = $factura->estado === EstadoFactura::Anulada;

                    $esRectificable = $factura->estado === EstadoFactura::Emitida && ! $factura->es_rectificativa && ! $factura->rectificativa()->exists();

                    $saldoPendiente = $factura->saldoPendiente();
                    $esEmitida = $factura->estado === EstadoFactura::Emitida;
                    $esCobrable = $esEmitida && $saldoPendiente > 0;

                    return [
                        'id' => $factura->id,
                        'identificador' => $factura->numero_completo ?? 'Borrador',
                        'estado' => $factura->estado->value,
                        'es_borrador' => $esBorrador,
                        'es_rectificativa' => $factura->es_rectificativa,
                        'cliente' => $factura->cliente->razon_social ?: $factura->cliente->nombre,
                        'cliente_email' => $factura->cliente->email,
                        'fecha_expedicion' => $factura->fecha_expedicion->toDateString(),
                        'total' => number_format((float) $factura->total, 2, '.', ''),
                        'estado_cobro' => $factura->estadoCobro()->value,
                        'saldo_pendiente' => number_format($saldoPendiente, 2, '.', ''),
                        'monto_cobrado' => number_format($factura->montoCobrado(), 2, '.', ''),
                        'pago_url' => $esCobrable ? route('facturas.pagos.store', $factura) : null,
                        'cobros_url' => $esEmitida ? route('facturas.pagos.index', $factura) : null,
                        'emitir_url' => $esBorrador ? route('facturas.emitir', $factura) : null,
                        'edit_url' => $esBorrador ? route('facturas.edit', $factura) : null,
                        'delete_url' => $esBorrador ? route('facturas.destroy', $factura) : null,
                        'rectificar_url' => $esRectificable ? route('facturas.rectificar', $factura) : null,
                        'pdf_url' => route('facturas.pdf', $factura),
                        'enviar_url' => (! $esBorrador && ! $esAnulada) ? route('facturas.enviar', $factura) : null,
                        'enviada' => $factura->fueEnviada(),
                    ];
                })->values(),
                'totales' => [
                    'total' => $facturas->count(),
                    'importe_total' => number_format((float) $facturas->sum('total'), 2, '.', ''),
                ],
                'email_configurado' => EmailTenant::estaConfigurado(tenant()->getTenantKey()),
            ]);
        }

        return view('facturas.index');
    }

    public function create(): View
    {
        return view('facturas.create', [
            'factura' => null,
            'clientes' => Cliente::orderBy('nombre')->get(),
            'cuentasBancarias' => CuentaBancaria::where('activa', true)->with('banco')->orderBy('alias')->get(),
            'diasVencimiento' => VencimientoFactura::diasPorDefecto(),
            'lineasIniciales' => [],
            'regimen' => TiposImpositivos::payloadVista(tenant()->regimen_impositivo),
        ]);
    }

    public function store(StoreFacturaRequest $request): RedirectResponse|JsonResponse
    {
        $datos = $request->validated();
        $cliente = Cliente::findOrFail($datos['cliente_id']);
        $serie = Serie::activaPorTipo(TipoFactura::Ordinaria);

        $factura = $this->guardar($datos, $cliente, $serie);

        $this->registradorActividad->registrar(
            auth()->user(),
            AccionLogActividad::Alta,
            EntidadLogActividad::Factura,
            $factura->id,
            "Creó la factura en borrador #{$factura->id}",
        );

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Factura creada correctamente.', 'id' => $factura->id], 201);
        }

        return redirect()->route('facturas.index')->with('success', 'Factura creada correctamente.');
    }

    public function edit(string $factura): View
    {
        $factura = Factura::with('lineas')->findOrFail($factura);

        if ($factura->estado !== EstadoFactura::Borrador) {
            abort(403, 'Solo se pueden editar facturas en borrador.');
        }

        return view('facturas.create', [
            'factura' => $factura,
            'clientes' => Cliente::orderBy('nombre')->get(),
            'cuentasBancarias' => CuentaBancaria::where('activa', true)->with('banco')->orderBy('alias')->get(),
            'diasVencimiento' => VencimientoFactura::diasPorDefecto(),
            'lineasIniciales' => $factura->lineas->map(fn ($linea) => [
                'articulo_id' => $linea->articulo_id,
                'concepto' => $linea->concepto,
                'unidad' => $linea->unidad,
                'cantidad' => (float) $linea->cantidad,
                'precio_unitario' => (float) $linea->precio_unitario,
                'descuento_porcentaje' => $linea->descuento_porcentaje !== null ? (float) $linea->descuento_porcentaje : null,
                'tipo_impositivo' => (float) $linea->tipo_impositivo,
            ])->values(),
            'regimen' => TiposImpositivos::payloadVista(tenant()->regimen_impositivo),
        ]);
    }

    public function update(UpdateFacturaRequest $request, string $factura): RedirectResponse|JsonResponse
    {
        $factura = Factura::findOrFail($factura);

        if ($factura->estado !== EstadoFactura::Borrador) {
            abort(403, 'Solo se pueden editar facturas en borrador.');
        }

        $datos = $request->validated();
        $cliente = Cliente::findOrFail($datos['cliente_id']);

        $this->guardar($datos, $cliente, $factura->serie, $factura);

        $this->registradorActividad->registrar(
            auth()->user(),
            AccionLogActividad::Modificacion,
            EntidadLogActividad::Factura,
            $factura->id,
            "Modificó la factura en borrador #{$factura->id}",
        );

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Factura actualizada correctamente.']);
        }

        return redirect()->route('facturas.index')->with('success', 'Factura actualizada correctamente.');
    }

    public function destroy(Request $request, string $factura): RedirectResponse|JsonResponse
    {
        $factura = Factura::findOrFail($factura);

        if ($factura->estado !== EstadoFactura::Borrador) {
            abort(403, 'Solo se pueden eliminar facturas en borrador.');
        }

        $facturaId = $factura->id;
        $factura->delete();

        $this->registradorActividad->registrar(
            auth()->user(),
            AccionLogActividad::Baja,
            EntidadLogActividad::Factura,
            $facturaId,
            "Eliminó la factura en borrador #{$facturaId}",
        );

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Factura eliminada correctamente.']);
        }

        return redirect()->route('facturas.index')->with('success', 'Factura eliminada correctamente.');
    }

    public function emitir(Request $request, string $factura): RedirectResponse|JsonResponse
    {
        $factura = Factura::findOrFail($factura);

        try {
            $factura = $this->emisor->emitir($factura);
        } catch (FacturaNoEmitibleException $e) {
            if ($request->wantsJson()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return redirect()->back()->with('error', $e->getMessage());
        }

        $mensaje = $factura->es_rectificativa ? 'Factura rectificativa emitida correctamente.' : 'Factura emitida correctamente.';

        $this->registradorActividad->registrar(
            auth()->user(),
            AccionLogActividad::Modificacion,
            EntidadLogActividad::Factura,
            $factura->id,
            "Emitió la factura {$factura->numero_completo}",
        );

        if ($request->wantsJson()) {
            return response()->json(['message' => $mensaje, 'numero_completo' => $factura->numero_completo]);
        }

        return redirect()->route('facturas.index')->with('success', $mensaje);
    }

    public function rectificar(StoreRectificativaRequest $request, string $factura): RedirectResponse|JsonResponse
    {
        $original = Factura::findOrFail($factura);
        $datos = $request->validated();

        try {
            $rectificativa = $this->generadorRectificativa->generar(
                $original,
                TipoRectificacion::from($datos['tipo_rectificacion']),
                $datos['motivo_rectificacion'],
            );
        } catch (FacturaNoRectificableException $e) {
            if ($request->wantsJson()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return redirect()->back()->with('error', $e->getMessage());
        }

        $this->registradorActividad->registrar(
            auth()->user(),
            AccionLogActividad::Modificacion,
            EntidadLogActividad::Factura,
            $original->id,
            "Rectificó la factura {$original->numero_completo} mediante #{$rectificativa->id}",
        );

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Rectificativa creada correctamente.', 'id' => $rectificativa->id], 201);
        }

        return redirect()->route('facturas.edit', $rectificativa)->with('success', 'Rectificativa creada correctamente.');
    }

    public function pdf(string $factura): Response
    {
        $factura = Factura::with(['lineas', 'impuestos', 'cliente', 'tenant', 'facturaRectificada', 'rectificativa', 'pagos'])->findOrFail($factura);

        $pdf = Pdf::loadView('facturas.pdf', ['factura' => $factura]);

        return $pdf->stream(($factura->numero_completo ?? 'borrador-'.$factura->id).'.pdf');
    }

    /**
     * Envío síncrono (deuda técnica asumida, ver docs/01-arquitectura.md Decisión 5): migrar a
     * ShouldQueue cuando haya worker de colas disponible o el volumen lo justifique.
     */
    public function enviar(Request $request, string $factura): RedirectResponse|JsonResponse
    {
        $factura = Factura::with(['lineas', 'impuestos', 'cliente', 'tenant', 'facturaRectificada', 'rectificativa', 'pagos'])->findOrFail($factura);

        if (in_array($factura->estado, [EstadoFactura::Borrador, EstadoFactura::Anulada], true)) {
            $mensaje = 'Solo se pueden enviar facturas emitidas.';

            if ($request->wantsJson()) {
                return response()->json(['message' => $mensaje], 422);
            }

            return redirect()->back()->with('error', $mensaje);
        }

        $request->validate([
            'destinatario' => ['required', 'email'],
        ]);

        $destinatario = $request->string('destinatario')->toString();

        try {
            $tenantMailer = new TenantMailer(tenant()->getTenantKey());
        } catch (EmailNoConfiguradoException $e) {
            $mensaje = 'Configura primero tu correo.';

            if ($request->wantsJson()) {
                return response()->json(['message' => $mensaje], 422);
            }

            return redirect()->back()->with('error', $mensaje);
        }

        $pdf = Pdf::loadView('facturas.pdf', ['factura' => $factura])->output();

        $mailable = (new FacturaMail($factura, $pdf))
            ->from($tenantMailer->remitente(), $tenantMailer->remitenteNombre());

        if ($tenantMailer->responderA()) {
            $mailable->replyTo($tenantMailer->responderA());
        }

        try {
            $tenantMailer->mailer()->to($destinatario)->send($mailable);
        } catch (\Throwable $e) {
            FacturaEvento::create([
                'tenant_id' => $factura->tenant_id,
                'factura_id' => $factura->id,
                'tipo_evento' => 'envio_email',
                'detalle' => ['destinatario' => $destinatario, 'resultado' => 'error', 'error' => $e->getMessage()],
                'ocurrido_at' => now(),
            ]);

            $mensaje = 'No se pudo enviar el correo. Revisa la configuración de tu servidor de email.';

            if ($request->wantsJson()) {
                return response()->json(['message' => $mensaje], 502);
            }

            return redirect()->back()->with('error', $mensaje);
        }

        FacturaEvento::create([
            'tenant_id' => $factura->tenant_id,
            'factura_id' => $factura->id,
            'tipo_evento' => 'envio_email',
            'detalle' => ['destinatario' => $destinatario, 'resultado' => 'ok'],
            'ocurrido_at' => now(),
        ]);

        $mensaje = "Factura enviada a {$destinatario}.";

        if ($request->wantsJson()) {
            return response()->json(['message' => $mensaje]);
        }

        return redirect()->back()->with('success', $mensaje);
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function guardar(array $datos, Cliente $cliente, Serie $serie, ?Factura $factura = null): Factura
    {
        $regimen = tenant()->regimen_impositivo;
        $aplicaRecargo = $cliente->aplica_recargo_equivalencia;

        $resultado = $this->calculadora->calcular(
            regimen: $regimen,
            aplicaRecargo: $aplicaRecargo,
            irpfPorcentaje: isset($datos['irpf_porcentaje']) ? (float) $datos['irpf_porcentaje'] : null,
            lineas: collect($datos['lineas'])->map(fn (array $linea) => [
                'cantidad' => (float) $linea['cantidad'],
                'precioUnitario' => (float) $linea['precio_unitario'],
                'descuentoPorcentaje' => isset($linea['descuento_porcentaje']) ? (float) $linea['descuento_porcentaje'] : null,
                'tipoImpositivo' => (float) $linea['tipo_impositivo'],
            ])->all(),
        );

        // Snapshot bancario congelado: solo cuando la forma de pago es transferencia y se ha
        // elegido una cuenta del tenant. En cualquier otro caso los 4 campos quedan a null.
        $snapshotBancario = [
            'cuenta_bancaria_id' => null,
            'cuenta_bancaria_banco' => null,
            'cuenta_bancaria_iban' => null,
            'cuenta_bancaria_titular' => null,
        ];

        if (($datos['forma_pago'] ?? null) === FormaPago::Transferencia->value && ! empty($datos['cuenta_bancaria_id'])) {
            $cuenta = CuentaBancaria::with('banco')->find($datos['cuenta_bancaria_id']);

            if ($cuenta) {
                $snapshotBancario = [
                    'cuenta_bancaria_id' => $cuenta->id,
                    'cuenta_bancaria_banco' => $cuenta->banco?->nombre,
                    'cuenta_bancaria_iban' => $cuenta->iban,
                    'cuenta_bancaria_titular' => $cuenta->titular,
                ];
            }
        }

        return DB::transaction(function () use ($datos, $cliente, $serie, $factura, $regimen, $aplicaRecargo, $resultado, $snapshotBancario) {
            $esRectificativa = $factura?->es_rectificativa ?? false;
            $esDiferencias = $esRectificativa && $factura->tipo_rectificacion === TipoRectificacion::Diferencias;

            $baseTotal = $resultado->baseTotal;
            $cuotaImpuestoTotal = $resultado->cuotaImpuestoTotal;
            $cuotaRecargoTotal = $resultado->cuotaRecargoTotal;
            $irpfCuota = $resultado->irpfCuota;
            $total = $resultado->total;
            $impuestos = $resultado->impuestos;

            if ($esDiferencias) {
                $original = $factura->facturaRectificada;

                $baseTotal = round($baseTotal - (float) $original->base_total, 2);
                $cuotaImpuestoTotal = round($cuotaImpuestoTotal - (float) $original->cuota_impuesto_total, 2);
                $cuotaRecargoTotal = round($cuotaRecargoTotal - (float) $original->cuota_recargo_total, 2);
                $irpfCuota = round($irpfCuota - (float) $original->irpf_cuota, 2);
                $total = round($total - (float) $original->total, 2);

                $impuestosOriginal = $original->impuestos->keyBy(fn ($i) => $i->tipo_impuesto->value.'|'.$i->porcentaje);

                $impuestos = collect($impuestos)->map(function (array $impuesto) use ($impuestosOriginal) {
                    $clave = $impuesto['tipoImpuesto'].'|'.$impuesto['porcentaje'];
                    $original = $impuestosOriginal->get($clave);

                    return [
                        'tipoImpuesto' => $impuesto['tipoImpuesto'],
                        'porcentaje' => $impuesto['porcentaje'],
                        'baseImponible' => round($impuesto['baseImponible'] - ($original ? (float) $original->base_imponible : 0), 2),
                        'cuota' => round($impuesto['cuota'] - ($original ? (float) $original->cuota : 0), 2),
                    ];
                })->all();
            }

            $cabecera = [
                'serie_id' => $serie->id,
                'tipo' => $esRectificativa ? TipoFactura::Rectificativa : TipoFactura::Ordinaria,
                'estado' => EstadoFactura::Borrador,
                'cliente_id' => $cliente->id,
                'cliente_nombre' => $datos['cliente_nombre'] ?? $cliente->nombre,
                'cliente_razon_social' => $datos['cliente_razon_social'] ?? $cliente->razon_social,
                'cliente_nif' => $datos['cliente_nif'] ?? $cliente->nif,
                'cliente_direccion' => $datos['cliente_direccion'] ?? $cliente->direccion,
                'cliente_cp' => $datos['cliente_cp'] ?? $cliente->cp,
                'cliente_ciudad' => $datos['cliente_ciudad'] ?? $cliente->ciudad,
                'cliente_provincia' => $datos['cliente_provincia'] ?? $cliente->provincia,
                'cliente_pais' => $datos['cliente_pais'] ?? $cliente->pais,
                'fecha_expedicion' => $datos['fecha_expedicion'],
                'fecha_operacion' => $datos['fecha_operacion'] ?? null,
                'fecha_vencimiento' => $datos['fecha_vencimiento'] ?? VencimientoFactura::calcular($datos['fecha_expedicion']),
                'forma_pago' => $datos['forma_pago'],
                'cuenta_bancaria_id' => $snapshotBancario['cuenta_bancaria_id'],
                'cuenta_bancaria_banco' => $snapshotBancario['cuenta_bancaria_banco'],
                'cuenta_bancaria_iban' => $snapshotBancario['cuenta_bancaria_iban'],
                'cuenta_bancaria_titular' => $snapshotBancario['cuenta_bancaria_titular'],
                'moneda' => 'EUR',
                'regimen_impositivo' => $regimen,
                'aplica_recargo' => $aplicaRecargo,
                'base_total' => $baseTotal,
                'cuota_impuesto_total' => $cuotaImpuestoTotal,
                'cuota_recargo_total' => $cuotaRecargoTotal,
                'irpf_porcentaje' => $datos['irpf_porcentaje'] ?? null,
                'irpf_cuota' => $irpfCuota,
                'total' => $total,
                'notas' => $datos['notas'] ?? null,
            ];

            if ($factura) {
                $factura->update($cabecera);
                $factura->lineas()->delete();
                $factura->impuestos()->delete();
            } else {
                $factura = Factura::create($cabecera);
            }

            foreach ($datos['lineas'] as $orden => $lineaDatos) {
                $calculo = $resultado->lineas[$orden];

                $factura->lineas()->create([
                    'articulo_id' => $lineaDatos['articulo_id'] ?? null,
                    'concepto' => $lineaDatos['concepto'],
                    'unidad' => $lineaDatos['unidad'] ?? null,
                    'cantidad' => $lineaDatos['cantidad'],
                    'precio_unitario' => $lineaDatos['precio_unitario'],
                    'descuento_porcentaje' => $lineaDatos['descuento_porcentaje'] ?? null,
                    'base' => $calculo['base'],
                    'tipo_impositivo' => $lineaDatos['tipo_impositivo'],
                    'cuota_impuesto' => $calculo['cuotaImpuesto'],
                    'tipo_recargo' => $calculo['tipoRecargo'],
                    'cuota_recargo' => $calculo['cuotaRecargo'],
                    'orden' => $orden,
                ]);
            }

            foreach ($impuestos as $impuesto) {
                $factura->impuestos()->create([
                    'tipo_impuesto' => $impuesto['tipoImpuesto'],
                    'porcentaje' => $impuesto['porcentaje'],
                    'base_imponible' => $impuesto['baseImponible'],
                    'cuota' => $impuesto['cuota'],
                ]);
            }

            return $factura;
        });
    }
}
