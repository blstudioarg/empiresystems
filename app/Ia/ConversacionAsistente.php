<?php

namespace App\Ia;

use App\Models\AsistenteConversacion;
use App\Models\AsistenteMensaje;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Historial de la conversación del asistente (feature 030, reescrito por la feature 045).
 *
 * **Ya no es efímero.** Los turnos viven en `asistente_conversaciones` / `asistente_mensajes`, así
 * que sobreviven al cierre de sesión; la sesión conserva solo el id de la conversación activa y la
 * acción pendiente (research D1, D2). Es un cambio deliberado de una decisión previa: por eso la
 * feature trae plazo de retención (`asistente.retencion_dias`, 90 días) y purga
 * (`asistente:purgar`), que el Principio II exige desde el primer diseño.
 *
 * La interfaz pública se mantiene igual que cuando el respaldo era la sesión, para que `AsistenteIa`
 * y el controlador no se enteren del cambio.
 *
 * Reglas que se conservan del diseño original:
 * - Ventana por número de mensajes (`config('ia.max_mensajes')`), preservando pares
 *   assistant(tool_calls)/tool completos: nunca un mensaje `tool` huérfano, nunca arrancar en
 *   assistant o tool. Ahora la ventana se aplica **al leer**, no borrando filas.
 * - Máx. 1 acción pendiente, que se invalida al confirmar, cancelar, enviar mensaje nuevo,
 *   reiniciar o **cambiar de conversación** (FR-023).
 */
class ConversacionAsistente
{
    private const CLAVE_SESION = 'asistente.conversacion';

    private const CLAVE_ACTIVA = self::CLAVE_SESION.'.conversacion_id';

    private const CLAVE_ACCION = self::CLAVE_SESION.'.accion_pendiente';

    /** Longitud máxima del título derivado del primer mensaje (research D7). */
    private const LARGO_TITULO = 60;

    // --- Conversación activa -------------------------------------------------

    public function idActivo(): ?int
    {
        $id = session()->get(self::CLAVE_ACTIVA);

        return $id !== null ? (int) $id : null;
    }

    /**
     * Conversación activa, o `null` si no hay ninguna o dejó de existir (borrada a mano o purgada:
     * en ambos casos el panel debe seguir funcionando, con un hilo nuevo y vacío).
     */
    public function activa(): ?AsistenteConversacion
    {
        $id = $this->idActivo();

        if ($id === null) {
            return null;
        }

        $conversacion = $this->buscarPropia($id);

        if ($conversacion === null) {
            // Se la llevó la purga o un borrado desde otra pestaña: olvidamos la referencia muerta.
            session()->forget(self::CLAVE_ACTIVA);
        }

        return $conversacion;
    }

    public function activar(AsistenteConversacion $conversacion): void
    {
        session()->put(self::CLAVE_ACTIVA, $conversacion->id);
    }

    /**
     * Busca una conversación acotada al tenant activo **y a la persona autenticada**: el scope de
     * tenant no separa a dos personas de la misma empresa, y el historial es privado de cada una.
     */
    public function buscarPropia(int $id): ?AsistenteConversacion
    {
        $usuario = $this->usuario();

        if ($usuario === null) {
            return null;
        }

        return AsistenteConversacion::query()
            ->paraUsuario($usuario)
            ->find($id);
    }

    // --- Lectura del hilo ----------------------------------------------------

    /**
     * Mensajes en el formato que consume el proveedor, listos para `messages`.
     *
     * Si la conversación está compactada, el resumen va delante como mensaje de sistema: es
     * contexto para el modelo, no contenido que el usuario lea.
     *
     * @return array<int, array<string, mixed>>
     */
    public function mensajes(): array
    {
        $conversacion = $this->activa();

        if ($conversacion === null) {
            return [];
        }

        $filas = $conversacion->mensajes()
            ->when(
                $conversacion->resumido_hasta_mensaje_id !== null,
                fn ($q) => $q->where('id', '>', $conversacion->resumido_hasta_mensaje_id),
            )
            ->get();

        $mensajes = $this->ventana(
            $filas->map(fn (AsistenteMensaje $m) => $m->aFormatoProveedor())->all()
        );

        if ($conversacion->estaCompactada()) {
            array_unshift($mensajes, [
                'role' => 'system',
                'content' => "Resumen de la parte anterior de esta conversación:\n".$conversacion->resumen,
            ]);
        }

        return $mensajes;
    }

    /**
     * Recorta a los últimos `ia.max_mensajes` sin dejar un `tool` huérfano ni arrancar en
     * assistant/tool. Es el mismo criterio que aplicaba el truncado en sesión, movido a la lectura.
     *
     * @param  array<int, array<string, mixed>>  $mensajes
     * @return array<int, array<string, mixed>>
     */
    private function ventana(array $mensajes): array
    {
        $max = (int) config('ia.max_mensajes', 30);

        while (count($mensajes) > $max) {
            array_shift($mensajes);

            while ($mensajes !== [] && ! $this->esTurnoUsuarioInicial($mensajes[0])) {
                array_shift($mensajes);
            }
        }

        return array_values($mensajes);
    }

    /**
     * @param  array<string, mixed>  $mensaje
     */
    private function esTurnoUsuarioInicial(array $mensaje): bool
    {
        return ($mensaje['role'] ?? null) === 'user';
    }

    // --- Escritura del hilo --------------------------------------------------

    /**
     * Agrega un turno. Si no hay conversación activa, la crea con el título derivado del primer
     * mensaje del usuario (research D7): así una conversación no existe hasta que la persona
     * escribe algo (FR-008), y el panel abierto sin escribir no ensucia el historial.
     *
     * @param  string|array<int, array<string, mixed>>  $content
     */
    public function agregar(string $role, string|array $content): void
    {
        $texto = is_string($content) ? $content : (string) json_encode($content, JSON_UNESCAPED_UNICODE);

        $this->escribir($role, $texto, [], $texto);
    }

    /**
     * Agrega un mensaje ya armado en formato del proveedor (con `tool_calls` u otras claves).
     *
     * @param  array<string, mixed>  $mensaje
     */
    public function agregarCrudo(array $mensaje): void
    {
        $rol = (string) ($mensaje['role'] ?? AsistenteMensaje::ROL_ASSISTANT);
        $contenido = $mensaje['content'] ?? null;

        $extra = $mensaje;
        unset($extra['role'], $extra['content']);

        $this->escribir($rol, is_string($contenido) ? $contenido : null, $extra);
    }

    /**
     * Nota del sistema dirigida al modelo ("la acción se confirmó", "el usuario canceló"). Entra en
     * el contexto pero queda fuera de lo que se le pinta al usuario.
     */
    public function agregarNotaInterna(string $contenido): void
    {
        $this->escribir(AsistenteMensaje::ROL_USER, $contenido, [AsistenteMensaje::META_INTERNO => true]);
    }

    public function agregarMensajeTool(string $toolCallId, string $contenido): void
    {
        $this->escribir(AsistenteMensaje::ROL_TOOL, $contenido, ['tool_call_id' => $toolCallId]);
    }

    /**
     * @param  array<string, mixed>  $metadatos
     * @param  string|null  $textoParaTitulo  Si se pasa y hay que crear la conversación, da el título.
     */
    private function escribir(string $rol, ?string $contenido, array $metadatos, ?string $textoParaTitulo = null): void
    {
        $usuario = $this->usuario();

        if ($usuario === null) {
            return; // sin persona autenticada no hay historial que escribir
        }

        $conversacion = $this->activa() ?? $this->crear($usuario, $textoParaTitulo ?? '');

        $conversacion->mensajes()->create([
            'tenant_id' => $conversacion->tenant_id,
            'rol' => $rol,
            'contenido' => $contenido,
            'metadatos' => $metadatos === [] ? null : $metadatos,
        ]);

        // Base del orden del listado (FR-005) y del criterio de purga (research D8).
        $conversacion->forceFill(['ultima_actividad_en' => now()])->save();
    }

    public function crear(User $usuario, string $textoParaTitulo = ''): AsistenteConversacion
    {
        $conversacion = AsistenteConversacion::create([
            'tenant_id' => $usuario->tenant_id,
            'user_id' => $usuario->id,
            'titulo' => self::tituloDesde($textoParaTitulo),
            'ultima_actividad_en' => now(),
        ]);

        $this->activar($conversacion);

        return $conversacion;
    }

    /**
     * Título = primer mensaje recortado en el borde de palabra (research D7). Sin llamadas al
     * proveedor: titular con IA quedó explícitamente fuera de alcance.
     */
    public static function tituloDesde(string $texto): string
    {
        $limpio = trim(preg_replace('/\s+/u', ' ', $texto) ?? '');

        if ($limpio === '') {
            return 'Conversación nueva';
        }

        return Str::limit($limpio, self::LARGO_TITULO - 1, '…');
    }

    /**
     * Ya no borra filas: la ventana se aplica al leer (ver `ventana()`). Se conserva porque
     * `AsistenteIa` la llama en su `finally` y porque es el fallback cuando la compactación falla
     * (FR-013) — en ambos casos el efecto deseado es exactamente "quédate con los últimos N", que
     * es lo que `mensajes()` ya hace.
     */
    public function truncar(): void
    {
        // Intencionadamente vacío. Ver el docblock.
    }

    // --- Compactación (feature 045, US2) -------------------------------------

    /**
     * Sustituye por un resumen todo lo anterior a `hastaMensajeId`.
     */
    public function aplicarCompactacion(string $resumen, int $hastaMensajeId): void
    {
        $conversacion = $this->activa();

        if ($conversacion === null) {
            return;
        }

        $conversacion->forceFill([
            'resumen' => $resumen,
            'resumido_hasta_mensaje_id' => $hastaMensajeId,
        ])->save();
    }

    // --- Acción pendiente ----------------------------------------------------

    /**
     * Guarda una propuesta de escritura como acción pendiente (máx. 1). Devuelve su id.
     *
     * Se anota en qué conversación se propuso: es lo que permite descartarla al cambiar de hilo
     * (FR-023) y no ejecutar nunca una escritura fuera de su contexto.
     *
     * @param  array<string, mixed>  $parametros
     */
    public function proponerAccion(string $tool, array $parametros, string $resumen, ?string $url = null): string
    {
        return $this->proponerAcciones([[
            'tool' => $tool,
            'parametros' => $parametros,
            'resumen' => $resumen,
            'url' => $url,
        ]]);
    }

    /**
     * Guarda un lote de escrituras como **una sola** propuesta pendiente.
     *
     * Sigue habiendo como máximo una propuesta viva por turno; lo que cambia es que puede contener
     * varias acciones. Pedir "creá 10 clientes" y tener que confirmar diez tarjetas seguidas no era
     * una confirmación más segura, solo más tediosa: el usuario revisa la lista entera y confirma una
     * vez, que es exactamente la garantía que pide D4.
     *
     * @param  array<int, array{tool: string, parametros: array<string, mixed>, resumen: string, url?: string|null}>  $acciones
     */
    public function proponerAcciones(array $acciones): string
    {
        $id = (string) Str::uuid();

        session()->put(self::CLAVE_ACCION, [
            'id' => $id,
            'acciones' => array_values($acciones),
            'conversacion_id' => $this->idActivo(),
            'creada_en' => now()->toIso8601String(),
        ]);

        return $id;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function accionPendiente(): ?array
    {
        $pendiente = session()->get(self::CLAVE_ACCION);

        if ($pendiente === null) {
            return null;
        }

        // La propuesta pertenece al hilo donde se hizo: si el panel cambió de conversación, deja de
        // ser válida (FR-023).
        if (($pendiente['conversacion_id'] ?? null) !== $this->idActivo()) {
            $this->limpiarAccion();

            return null;
        }

        return $pendiente;
    }

    public function hayAccionPendiente(): bool
    {
        return $this->accionPendiente() !== null;
    }

    public function limpiarAccion(): void
    {
        session()->forget(self::CLAVE_ACCION);
    }

    /**
     * Conversación nueva: se olvida la activa y la acción pendiente. **No borra el historial**: el
     * hilo anterior queda guardado y accesible desde el historial (FR-007).
     */
    public function reiniciar(): void
    {
        session()->forget(self::CLAVE_SESION);
    }

    private function usuario(): ?User
    {
        $usuario = auth()->user();

        return $usuario instanceof User ? $usuario : null;
    }
}
