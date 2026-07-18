<?php

namespace App\Ia;

use Illuminate\Support\Str;

/**
 * Historial de la conversación del asistente, en la sesión de Laravel (feature 030, D5/data-model §2).
 *
 * Efímero por diseño: sobrevive a la navegación, muere con la sesión (RGPD sin retención extra).
 * Mensajes en formato Chat Completions de OpenAI (roles user/assistant/tool; el assistant puede
 * llevar `tool_calls` y los resultados van como mensajes de rol `tool`).
 *
 * Reglas:
 * - Truncado por número de mensajes (`config('ia.max_mensajes')`), preservando pares
 *   assistant(tool_calls)/tool completos (nunca un mensaje `tool` huérfano, nunca arrancar en
 *   assistant o tool).
 * - Máx. 1 acción pendiente. Se invalida al confirmar, cancelar, enviar mensaje nuevo o reiniciar.
 */
class ConversacionAsistente
{
    private const CLAVE_SESION = 'asistente.conversacion';

    /**
     * @return array<int, array<string, mixed>>
     */
    public function mensajes(): array
    {
        return session()->get(self::CLAVE_SESION.'.mensajes', []);
    }

    /**
     * Agrega un turno a la conversación.
     *
     * @param  string|array<int, array<string, mixed>>  $content
     */
    public function agregar(string $role, string|array $content): void
    {
        $mensajes = $this->mensajes();
        $mensajes[] = ['role' => $role, 'content' => $content];

        session()->put(self::CLAVE_SESION.'.mensajes', $mensajes);
    }

    /**
     * Agrega un mensaje ya armado (formato OpenAI completo, con su `role` y demás claves).
     *
     * @param  array<string, mixed>  $mensaje
     */
    public function agregarCrudo(array $mensaje): void
    {
        $mensajes = $this->mensajes();
        $mensajes[] = $mensaje;

        session()->put(self::CLAVE_SESION.'.mensajes', $mensajes);
    }

    /**
     * Agrega el resultado de una tool como mensaje de rol `tool` (formato OpenAI).
     */
    public function agregarMensajeTool(string $toolCallId, string $contenido): void
    {
        $mensajes = $this->mensajes();
        $mensajes[] = ['role' => 'tool', 'tool_call_id' => $toolCallId, 'content' => $contenido];

        session()->put(self::CLAVE_SESION.'.mensajes', $mensajes);
    }

    /**
     * Descarta los turnos más antiguos superando el umbral, preservando pares tool_use/tool_result
     * y garantizando que el primer mensaje sea de rol `user` sin bloques tool_result.
     */
    public function truncar(): void
    {
        $max = (int) config('ia.max_mensajes', 30);
        $mensajes = $this->mensajes();

        while (count($mensajes) > $max) {
            array_shift($mensajes);
            // Descartar hasta que el primer mensaje sea un turno de usuario "limpio".
            while (! empty($mensajes) && ! $this->esTurnoUsuarioInicial($mensajes[0])) {
                array_shift($mensajes);
            }
        }

        session()->put(self::CLAVE_SESION.'.mensajes', array_values($mensajes));
    }

    /**
     * Un turno válido para arrancar la conversación: un mensaje de rol `user`. Los mensajes de rol
     * `assistant` (que pueden llevar tool_calls) y `tool` (resultados) nunca pueden ir primeros.
     *
     * @param  array<string, mixed>  $mensaje
     */
    private function esTurnoUsuarioInicial(array $mensaje): bool
    {
        return ($mensaje['role'] ?? null) === 'user';
    }

    // --- Acción pendiente ---------------------------------------------------

    /**
     * Guarda una propuesta de escritura como acción pendiente (máx. 1). Devuelve su id.
     *
     * @param  array<string, mixed>  $parametros
     */
    public function proponerAccion(string $tool, array $parametros, string $resumen, ?string $url = null): string
    {
        $id = (string) Str::uuid();

        session()->put(self::CLAVE_SESION.'.accion_pendiente', [
            'id' => $id,
            'tool' => $tool,
            'parametros' => $parametros,
            'resumen' => $resumen,
            'url' => $url,
            'creada_en' => now()->toIso8601String(),
        ]);

        return $id;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function accionPendiente(): ?array
    {
        return session()->get(self::CLAVE_SESION.'.accion_pendiente');
    }

    public function hayAccionPendiente(): bool
    {
        return $this->accionPendiente() !== null;
    }

    public function limpiarAccion(): void
    {
        session()->forget(self::CLAVE_SESION.'.accion_pendiente');
    }

    public function reiniciar(): void
    {
        session()->forget(self::CLAVE_SESION);
    }
}
