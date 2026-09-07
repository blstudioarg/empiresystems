<?php

namespace App\Http\Controllers;

use App\Excel\BorradorImportacion;
use App\Excel\RegistroDefiniciones;
use App\Ia\ConversacionAsistente;
use App\Services\AlmacenImportaciones;
use App\Support\MaterialImportable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Material que la persona aporta al asistente para una importación (feature 046,
 * contracts/endpoints.md §1 y §2).
 *
 * Va aparte del endpoint de mensaje porque ese es SSE por streaming dentro del request, y meterle
 * un `multipart` con un PDF de 5 MB complica el flujo sin ganar nada (research D5). Separarlo
 * permite además rechazar tipo y tamaño con una explicación antes de gastar un turno de
 * conversación (FR-003).
 *
 * **Subir no analiza**: solo deja el material disponible. El análisis lo dispara el asistente con
 * su tool, para que el progreso se vea en la conversación.
 */
class AsistenteMaterialController extends Controller
{
    /** Mismo tope que la importación de la 031: 5 MB. */
    private const MAX_KB = 5120;

    public function __construct(
        private readonly RegistroDefiniciones $definiciones,
        private readonly AlmacenImportaciones $almacen,
        private readonly ConversacionAsistente $conversacion,
    ) {}

    /**
     * POST /asistente/material.
     */
    public function store(Request $request): JsonResponse
    {
        $usuario = $request->user();

        $datos = $request->validate([
            'fichero' => ['required', 'file', 'max:'.self::MAX_KB],
            'modulo' => ['required', 'string'],
            'token' => ['nullable', 'string'],
        ], [
            'fichero.required' => 'No llegó ningún fichero.',
            'fichero.max' => 'El fichero supera los 5 MB. Dividilo en partes más pequeñas.',
        ]);

        // Un módulo que no implementa `DefinicionImportable` no existe para esta ruta: la
        // prohibición de importar facturas o albaranes es cierta por construcción y no una lista
        // negra que alguien pueda olvidar de mantener (FR-010).
        try {
            $definicion = $this->definiciones->resolverImportable($datos['modulo']);
        } catch (NotFoundHttpException) {
            return response()->json([
                'mensaje' => "No se pueden importar «{$datos['modulo']}». Puedo con: "
                    .implode(', ', array_keys($this->definiciones->importables())).'.',
            ], 404);
        }

        // El mismo permiso que exige la pantalla de importación existente (FR-021).
        if (! $usuario->can($definicion->permiso())) {
            return response()->json(['mensaje' => 'No tenés permiso para importar '.$definicion->etiquetaModulo().'.'], 403);
        }

        /** @var UploadedFile $fichero */
        $fichero = $datos['fichero'];
        $extension = mb_strtolower($fichero->getClientOriginalExtension());

        if (! MaterialImportable::admitido($extension)) {
            return response()->json(['mensaje' => MaterialImportable::motivoRechazo($extension)], 422);
        }

        if ($fichero->getSize() === 0 || $fichero->getSize() === false) {
            return response()->json(['mensaje' => 'El fichero está vacío.'], 422);
        }

        $borrador = $this->resolverBorrador($request, $datos['token'] ?? null, $datos['modulo'], $extension);

        if ($borrador === null) {
            // Token ajeno, inexistente o caducado: 404 y nunca 403, que confirmaría que existe.
            return response()->json(['mensaje' => 'Esa importación ya no está disponible. Volvé a adjuntar el material.'], 404);
        }

        $tokenMaterial = $this->almacen->guardar($fichero);
        $borrador->anotarPendiente($tokenMaterial, $fichero->getClientOriginalName(), $extension);

        $this->almacen->guardarBorrador($borrador);

        return response()->json([
            'token' => $borrador->token,
            'modulo' => $borrador->modulo,
            'origen' => $borrador->origen,
            'nombre' => $fichero->getClientOriginalName(),
        ]);
    }

    /**
     * DELETE /asistente/material/{token} — descarta la importación en curso y borra su material.
     */
    public function destroy(Request $request, string $token): JsonResponse
    {
        $usuario = $request->user();

        $borrador = $this->almacen->borrador($token, (int) $usuario->tenant_id, (int) $usuario->id);

        if ($borrador === null) {
            return response()->json(['mensaje' => 'Esa importación ya no está disponible.'], 404);
        }

        $this->descartar($borrador);

        return response()->json(['ok' => true]);
    }

    /**
     * Borra el material y el borrador de una importación. Es dato personal de terceros: en cuanto
     * deja de hacer falta, se va (Principio II, minimización).
     */
    public function descartar(BorradorImportacion $borrador): void
    {
        foreach ($borrador->pendientes() as $pendiente) {
            $this->almacen->borrar($pendiente['token']);
        }

        $this->almacen->borrarBorrador($borrador->token);
    }

    /**
     * Con token, la importación en curso a la que se acumula el material (US3, escenario 4); sin
     * token, una nueva. Devuelve `null` si el token no es de quien lo pide.
     */
    private function resolverBorrador(Request $request, ?string $token, string $modulo, string $extension): ?BorradorImportacion
    {
        $usuario = $request->user();

        if ($token !== null && $token !== '') {
            $borrador = $this->almacen->borrador($token, (int) $usuario->tenant_id, (int) $usuario->id);

            if ($borrador === null || $borrador->modulo !== $modulo) {
                return null;
            }

            if (! $borrador->perteneceAlHilo($this->conversacion->idActivo())) {
                return null;
            }

            return $borrador;
        }

        return new BorradorImportacion(
            token: (string) Str::uuid(),
            tenantId: (int) $usuario->tenant_id,
            userId: (int) $usuario->id,
            // El material pertenece al hilo en el que se está trabajando: cambiar de conversación lo
            // descarta, igual que descarta una propuesta pendiente.
            conversacionId: $this->conversacion->idActivo(),
            modulo: $modulo,
            origen: MaterialImportable::origen($extension) ?? MaterialImportable::ORIGEN_HOJA,
            creadoEn: now()->toIso8601String(),
        );
    }
}
