<?php

namespace App\Http\Controllers;

use App\Enums\AccionLogActividad;
use App\Enums\EntidadLogActividad;
use App\Enums\EstadoCompra;
use App\Enums\OrigenCompra;
use App\Exceptions\DocumentoCompraException;
use App\Http\Requests\CrearCompraDesdeDocumentoRequest;
use App\Http\Requests\SubirDocumentosCompraRequest;
use App\Models\Compra;
use App\Models\Proveedor;
use App\Services\AlmacenDocumentosCompra;
use App\Services\InterpretadorDocumentoCompra;
use App\Services\ProponedorCompraDesdeDocumento;
use App\Services\RegistradorActividad;
use App\Support\ContadorPaginasPdf;
use App\Support\IaTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Importación de compras desde PDF/imagen interpretados por IA (feature 044).
 *
 * El flujo tiene tres pasos separados a propósito (research D5): subir no llama al modelo,
 * interpretar hace **una** llamada por documento, y crear es el único momento en que se toca la
 * base de datos. Así una petición nunca excede el `max_execution_time` de un hosting compartido y
 * un documento ilegible no invalida el resto del lote.
 */
class CompraDocumentoController extends Controller
{
    public function __construct(
        private readonly AlmacenDocumentosCompra $almacen,
        private readonly InterpretadorDocumentoCompra $interpretador,
        private readonly ProponedorCompraDesdeDocumento $proponedor,
        private readonly RegistradorActividad $registrador,
        private readonly ContadorPaginasPdf $contadorPaginas,
    ) {}

    /**
     * Guarda el lote como temporales. Valida antes de gastar una sola llamada al modelo.
     */
    public function subir(SubirDocumentosCompraRequest $request): JsonResponse
    {
        if (! IaTenant::configurada()) {
            return response()->json([
                'message' => 'El asistente IA no está configurado para esta empresa. Configúralo en Configuración › Asistente IA.',
                'codigo' => DocumentoCompraException::IA_NO_CONFIGURADA,
            ], 422);
        }

        $maxPaginas = (int) config('compras.documentos.max_paginas_pdf');
        $documentos = [];
        $rechazados = [];

        foreach ($request->file('archivos') as $archivo) {
            $nombre = $archivo->getClientOriginalName();
            $extension = strtolower($archivo->getClientOriginalExtension());

            if ($extension === 'pdf') {
                $paginas = $this->contadorPaginas->contar($archivo->getRealPath());

                // Un recuento indeterminado (null) no bloquea: research D7.
                if ($paginas !== null && $paginas > $maxPaginas) {
                    $rechazados[] = [
                        'archivo_nombre' => $nombre,
                        'motivo' => "El PDF supera el máximo de {$maxPaginas} páginas (tiene {$paginas}).",
                    ];

                    continue;
                }
            }

            $documentos[] = [
                'token' => $this->almacen->guardar($archivo),
                'archivo_nombre' => $nombre,
                'formato' => $extension === 'pdf' ? 'pdf' : 'imagen',
            ];
        }

        $cuerpo = ['documentos' => $documentos, 'rechazados' => $rechazados];

        // Un lote enteramente rechazado no es un éxito parcial: es un fallo de entrada.
        return response()->json($cuerpo, $documentos === [] ? 422 : 200);
    }

    /**
     * Interpreta un documento. Exactamente una llamada al modelo. Nada se persiste todavía.
     */
    public function interpretar(Request $request, string $token): JsonResponse
    {
        $ruta = $this->almacen->rutaAbsoluta($token);

        // 404 también si el token es de otro tenant: no debe poder confirmarse como existente.
        if ($ruta === null) {
            abort(404, 'El documento ya no está disponible.');
        }

        $nombre = $request->string('archivo_nombre')->toString() ?: basename($ruta);
        $extension = $this->almacen->extension($token) ?? 'pdf';

        try {
            $lectura = $this->interpretador->interpretar($ruta, $nombre, $extension);
        } catch (DocumentoCompraException $e) {
            return response()->json(array_filter([
                'message' => $e->getMessage(),
                'codigo' => $e->codigo,
                'archivo_nombre' => $nombre,
                'motivo' => $e->codigo === DocumentoCompraException::NO_ES_DOCUMENTO_COMPRA ? $e->detalle : null,
                // El detalle técnico solo para quien administra la configuración.
                'detalle' => $request->user()?->can('ver-configuracion') ? $e->detalle : null,
            ], static fn ($valor) => $valor !== null), $e->estadoHttp());
        }

        return response()->json($this->proponedor->proponer($lectura, $token, $nombre));
    }

    /**
     * Crea la compra desde la propuesta ya editada por el usuario. Único punto que escribe.
     */
    public function crear(CrearCompraDesdeDocumentoRequest $request, string $token): JsonResponse
    {
        $ruta = $this->almacen->rutaAbsoluta($token);

        if ($ruta === null) {
            abort(404, 'El documento ya no está disponible.');
        }

        $datos = $request->validated();

        // Se evalúa aquí de nuevo (y no solo al proponer) porque entre una cosa y otra otro usuario
        // pudo haber subido el mismo documento.
        if (! ($datos['confirmar_duplicado'] ?? false) && ! ($datos['crear_proveedor'] ?? false)) {
            $duplicada = $this->buscarDuplicada($datos);

            if ($duplicada) {
                return response()->json([
                    'message' => 'Ya existe una compra con el mismo proveedor, número y fecha.',
                    'codigo' => 'posible_duplicado',
                    'compra_existente' => [
                        'id' => $duplicada->id,
                        'show_url' => route('compras.show', $duplicada),
                        'fecha' => $duplicada->fecha?->toDateString(),
                        'total' => number_format((float) $duplicada->total, 2, '.', ''),
                    ],
                ], 409);
            }
        }

        $extension = $this->almacen->extension($token) ?? 'pdf';
        $rutaDestino = 'tenants/'.tenant()->getTenantKey().'/compras-documentos/'.Str::uuid()->toString().'.'.$extension;

        $compra = DB::transaction(function () use ($datos, $ruta, $rutaDestino, $extension) {
            $proveedorId = $datos['proveedor_id'] ?? null;

            if ($datos['crear_proveedor'] ?? false) {
                $proveedorId = Proveedor::create(array_filter(
                    $datos['proveedor_nuevo'],
                    static fn ($valor) => $valor !== null && $valor !== '',
                ))->id;
            }

            $baseTotal = 0.0;
            $cuotaTotal = 0.0;
            $lineas = [];

            // Mismos importes que el alta manual: el cliente no es fuente de verdad (Principio III).
            foreach ($datos['lineas'] as $orden => $linea) {
                $base = round((float) $linea['cantidad'] * (float) $linea['precio_unitario'], 2);
                $cuota = round($base * (float) $linea['tipo_impositivo'] / 100, 2);

                $baseTotal += $base;
                $cuotaTotal += $cuota;

                $lineas[] = [
                    'articulo_id' => $linea['articulo_id'] ?? null,
                    'concepto' => $linea['concepto'],
                    'unidad' => $linea['unidad'] ?? null,
                    'cantidad' => $linea['cantidad'],
                    'precio_unitario' => $linea['precio_unitario'],
                    'base' => $base,
                    'tipo_impositivo' => $linea['tipo_impositivo'],
                    'cuota_impuesto' => $cuota,
                    'orden' => $orden,
                ];
            }

            // El fichero se mueve dentro de la transacción: si algo falla después, no queda un
            // documento definitivo huérfano de compra.
            Storage::disk('documentos')->put($rutaDestino, file_get_contents($ruta));

            $compra = Compra::create([
                'proveedor_id' => $proveedorId,
                'numero_documento' => $datos['numero_documento'] ?? null,
                'fecha' => $datos['fecha'],
                'notas' => $datos['notas'] ?? null,
                'base_total' => round($baseTotal, 2),
                'cuota_impuesto_total' => round($cuotaTotal, 2),
                'total' => round($baseTotal + $cuotaTotal, 2),
                'estado' => EstadoCompra::Borrador,
                'origen' => OrigenCompra::Documento,
                'formato_recepcion' => $extension === 'pdf' ? 'pdf' : 'imagen',
                'archivo_recibido_path' => $rutaDestino,
            ]);

            foreach ($lineas as $linea) {
                $compra->lineas()->create($linea);
            }

            return $compra->refresh();
        });

        $this->almacen->borrar($token);

        if ($usuario = $request->user()) {
            $this->registrador->registrar(
                $usuario,
                AccionLogActividad::Alta,
                EntidadLogActividad::Compra,
                $compra->id,
                'Compra creada desde un documento interpretado por IA'
                    .($compra->numero_documento ? " ({$compra->numero_documento})" : ''),
            );
        }

        return response()->json([
            'message' => 'Compra creada correctamente desde el documento.',
            'id' => $compra->id,
            'show_url' => route('compras.show', $compra),
            'totales' => [
                'base_total' => number_format((float) $compra->base_total, 2, '.', ''),
                'cuota_impuesto_total' => number_format((float) $compra->cuota_impuesto_total, 2, '.', ''),
                'total' => number_format((float) $compra->total, 2, '.', ''),
            ],
        ], 201);
    }

    /**
     * Idempotente: descartar un token ya borrado no es un error. Nunca toca la base de datos.
     */
    public function descartar(string $token): JsonResponse
    {
        $this->almacen->borrar($token);

        return response()->json(['message' => 'Documento descartado.']);
    }

    public function descargar(string $compra): Response
    {
        $compra = Compra::findOrFail($compra);

        if ($compra->origen !== OrigenCompra::Documento || ! $compra->archivo_recibido_path) {
            abort(404, 'Esta compra no tiene un documento asociado.');
        }

        if (! Storage::disk('documentos')->exists($compra->archivo_recibido_path)) {
            abort(404, 'El documento ya no está disponible.');
        }

        $extension = strtolower(pathinfo($compra->archivo_recibido_path, PATHINFO_EXTENSION));

        return response(
            Storage::disk('documentos')->get($compra->archivo_recibido_path),
            200,
            [
                'Content-Type' => match ($extension) {
                    'pdf' => 'application/pdf',
                    'png' => 'image/png',
                    'webp' => 'image/webp',
                    default => 'image/jpeg',
                },
                'Content-Disposition' => 'attachment; filename="'
                    .($compra->numero_documento ?? 'compra-'.$compra->id).'.'.$extension.'"',
            ]
        );
    }

    /**
     * Mismo criterio que `ImportadorFacturae`: proveedor + número + fecha del mismo día.
     */
    private function buscarDuplicada(array $datos): ?Compra
    {
        if (empty($datos['proveedor_id']) || empty($datos['numero_documento'])) {
            return null;
        }

        return Compra::where('proveedor_id', $datos['proveedor_id'])
            ->where('numero_documento', $datos['numero_documento'])
            ->whereDate('fecha', $datos['fecha'])
            ->first();
    }
}
