<?php

namespace App\Services;

use App\Models\AsistenteConversacion;
use App\Models\AsistenteMensaje;
use App\Support\IaTenant;
use Illuminate\Support\Collection;

/**
 * Compactación de conversaciones largas del asistente (feature 045, US2).
 *
 * Cuando un hilo supera el umbral, en vez de descartar los turnos viejos —lo que hacía el truncado
 * y provocaba que el asistente olvidara de golpe lo hablado al principio— se pide al modelo un
 * resumen de esa parte y se guarda en la conversación.
 *
 * Igual que `InterpretadorDocumentoCompra` en la feature 044, esta es la **única** pieza que habla
 * con el proveedor para esto, y la lógica de negocio (dónde cortar) vive aparte y se prueba sin red.
 *
 * Dos invariantes que no se pueden romper:
 * - El corte nunca puede partir un par `assistant(tool_calls)` / `tool`: el proveedor rechaza un
 *   `tool` huérfano, y el fallo aparecería como un error opaco a mitad de conversación.
 * - Al recompactar, el resumen anterior entra como entrada del nuevo (FR-014); nunca se acumulan
 *   resúmenes sueltos.
 */
class CompactadorConversacion
{
    /** Turnos recientes que se conservan literales: es donde el usuario espera precisión. */
    public const MENSAJES_LITERALES = 10;

    private const SYSTEM_PROMPT = <<<'TXT'
    Resumís la parte antigua de una conversación entre una persona y el asistente de un programa de
    facturación, para que el asistente pueda seguir el hilo sin releerla entera.

    Reglas:
    - Conservá los datos concretos: nombres de clientes y proveedores, importes, números de factura,
      fechas, decisiones tomadas y tareas pendientes. Son lo que el asistente va a necesitar.
    - Conservá lo que la persona dijo sobre sí misma o sobre cómo quiere trabajar.
    - No inventes nada que no esté en la conversación.
    - No incluyas cortesías, saludos ni relleno.
    - Escribí en tercera persona, en un solo bloque de texto, sin encabezados ni listas.
    - Si te dan un resumen previo, integralo con lo nuevo en un único resumen: no lo repitas aparte.
    TXT;

    /**
     * ¿Toca compactar? Se compara contra el mismo umbral que ya usaba el truncado
     * (`ia.max_mensajes`), para no introducir un número nuevo sin fundamento.
     */
    public function debeCompactar(AsistenteConversacion $conversacion): bool
    {
        return $this->pendientes($conversacion)->count() > (int) config('ia.max_mensajes', 30);
    }

    /**
     * Compacta la conversación. Devuelve `null` si no había nada que hacer o si el corte no deja
     * ningún mensaje que resumir.
     *
     * @return array{resumen: string, hasta_mensaje_id: int}|null
     */
    public function compactar(AsistenteConversacion $conversacion): ?array
    {
        $pendientes = $this->pendientes($conversacion);
        $corte = self::calcularCorte($pendientes);

        if ($corte === null) {
            return null;
        }

        $aResumir = $pendientes->filter(fn (AsistenteMensaje $m) => $m->id <= $corte);

        if ($aResumir->isEmpty()) {
            return null;
        }

        $resumen = $this->pedirResumen($conversacion->resumen, $aResumir);

        return ['resumen' => $resumen, 'hasta_mensaje_id' => $corte];
    }

    /**
     * Mensajes todavía no resumidos, en orden.
     *
     * @return Collection<int, AsistenteMensaje>
     */
    private function pendientes(AsistenteConversacion $conversacion): Collection
    {
        return $conversacion->mensajes()
            ->when(
                $conversacion->resumido_hasta_mensaje_id !== null,
                fn ($q) => $q->where('id', '>', $conversacion->resumido_hasta_mensaje_id),
            )
            ->get();
    }

    /**
     * Id del último mensaje que entra en el resumen, dejando `MENSAJES_LITERALES` intactos al final.
     *
     * El corte se desplaza hacia atrás si cayera en medio de un par assistant(tool_calls)/tool: un
     * `tool` cuyo `assistant` quedó en el resumen es un mensaje huérfano y el proveedor lo rechaza.
     *
     * @param  Collection<int, AsistenteMensaje>  $mensajes
     */
    public static function calcularCorte(Collection $mensajes): ?int
    {
        $total = $mensajes->count();

        if ($total <= self::MENSAJES_LITERALES) {
            return null;
        }

        $ordenados = $mensajes->values();
        $indiceCorte = $total - self::MENSAJES_LITERALES; // primer índice que se conserva literal

        // Si el primer mensaje conservado es un `tool`, su `assistant` quedaría del otro lado.
        // Retrocedemos hasta que el bloque conservado empiece por algo que se sostenga solo.
        while ($indiceCorte > 0 && $ordenados[$indiceCorte]->rol === AsistenteMensaje::ROL_TOOL) {
            $indiceCorte--;
        }

        // Y si eso deja el corte justo detrás de un assistant con tool_calls, retrocedemos ese
        // assistant también: sus `tool` van con él.
        while ($indiceCorte > 0 && self::pideHerramientas($ordenados[$indiceCorte - 1])) {
            $indiceCorte--;
        }

        if ($indiceCorte <= 0) {
            return null; // no queda nada que resumir sin romper el hilo
        }

        return $ordenados[$indiceCorte - 1]->id;
    }

    private static function pideHerramientas(AsistenteMensaje $mensaje): bool
    {
        return $mensaje->rol === AsistenteMensaje::ROL_ASSISTANT
            && ! empty($mensaje->metadatos['tool_calls'] ?? null);
    }

    /**
     * @param  Collection<int, AsistenteMensaje>  $mensajes
     */
    private function pedirResumen(?string $resumenPrevio, Collection $mensajes): string
    {
        $transcripcion = $mensajes
            ->map(fn (AsistenteMensaje $m) => $this->comoLinea($m))
            ->filter()
            ->implode("\n");

        $entrada = $resumenPrevio
            ? "Resumen previo de esta conversación:\n{$resumenPrevio}\n\nContinuación a integrar:\n{$transcripcion}"
            : "Conversación a resumir:\n{$transcripcion}";

        return $this->pedirAlProveedor($entrada);
    }

    private function comoLinea(AsistenteMensaje $mensaje): ?string
    {
        if ($mensaje->rol === AsistenteMensaje::ROL_TOOL) {
            return null; // el resultado crudo de una consulta no aporta al resumen
        }

        if ($mensaje->contenido === null || trim($mensaje->contenido) === '') {
            return null;
        }

        $quien = $mensaje->rol === AsistenteMensaje::ROL_USER ? 'Persona' : 'Asistente';

        return "{$quien}: {$mensaje->contenido}";
    }

    /**
     * Único punto que toca la red. Sin streaming (el resumen no se muestra token a token) y sin
     * tools. Aislado en un método propio para poder sustituirlo en test, igual que `abrirStream()`
     * en `AsistenteIa`.
     */
    protected function pedirAlProveedor(string $entrada): string
    {
        $respuesta = \OpenAI::client(IaTenant::apiKey())->chat()->create([
            'model' => (string) config('ia.modelo'),
            'max_tokens' => 1024,
            'messages' => [
                ['role' => 'system', 'content' => self::SYSTEM_PROMPT],
                ['role' => 'user', 'content' => $entrada],
            ],
        ]);

        $resumen = trim((string) ($respuesta->choices[0]->message->content ?? ''));

        if ($resumen === '') {
            throw new \RuntimeException('El servicio de IA devolvió un resumen vacío.');
        }

        return $resumen;
    }
}
