<?php

namespace App\Services;

use App\Ia\CatalogoTools;
use App\Ia\ConocimientoAsistente;
use App\Ia\ConversacionAsistente;
use App\Models\User;
use App\Support\IaTenant;
use OpenAI\Client;
use OpenAI\Exceptions\ErrorException;
use OpenAI\Exceptions\RateLimitException;
use OpenAI\Exceptions\TransporterException;

/**
 * Orquestador del asistente IA (feature 030). Proveedor: OpenAI (Chat Completions con streaming +
 * function calling).
 *
 * Construye el cliente con la clave del tenant, arma el request (modelo de config/ia.php, system
 * prompt de ConocimientoAsistente, tools filtradas por CatalogoTools::paraUsuario) y ejecuta el loop
 * de tool use por streaming, notificando el progreso vía callbacks. Sin colas ni workers: llamada
 * síncrona dentro del request (Principio V).
 *
 * Las escrituras nunca se ejecutan aquí: se convierten en una acción pendiente que el usuario
 * confirma en un request separado (D4).
 *
 * @phpstan-type Callbacks array{
 *     texto?: callable(string): void,
 *     actividad?: callable(string): void,
 *     accionPendiente?: callable(array<string,mixed>): void,
 *     error?: callable(string,string,?string): void,
 * }
 */
class AsistenteIa
{
    public function __construct(
        private readonly ConversacionAsistente $conversacion,
        private readonly ConocimientoAsistente $conocimiento,
    ) {}

    /**
     * @param  Callbacks  $callbacks
     */
    public function responder(User $usuario, string $mensaje, array $callbacks): void
    {
        // Un mensaje nuevo descarta cualquier propuesta de escritura previa.
        $this->conversacion->limpiarAccion();
        $this->conversacion->agregar('user', $mensaje);

        try {
            $this->ejecutarLoop($usuario, $callbacks);
        } catch (ErrorException $e) {
            $codigo = $e->getStatusCode() === 401 ? 'clave_invalida' : ($e->getStatusCode() === 429 ? 'limite_excedido' : 'interno');
            $mensajeAmigable = match ($codigo) {
                'clave_invalida' => 'La clave de API configurada no es válida. Revisá la configuración del asistente.',
                'limite_excedido' => 'Se alcanzó el límite de uso del servicio de IA. Probá de nuevo en unos minutos.',
                default => 'Ocurrió un error al procesar tu mensaje.',
            };
            $this->error($callbacks, $codigo, $mensajeAmigable, $usuario, $e);
        } catch (RateLimitException $e) {
            $this->error($callbacks, 'limite_excedido', 'Se alcanzó el límite de uso del servicio de IA. Probá de nuevo en unos minutos.', $usuario, $e);
        } catch (TransporterException $e) {
            $this->error($callbacks, 'servicio_no_disponible', 'No se pudo conectar con el servicio de IA. Probá de nuevo en unos instantes.', $usuario, $e);
        } catch (\Throwable $e) {
            $this->error($callbacks, 'interno', 'Ocurrió un error al procesar tu mensaje.', $usuario, $e);
        } finally {
            $this->conversacion->truncar();
        }
    }

    /**
     * @param  Callbacks  $callbacks
     */
    private function ejecutarLoop(User $usuario, array $callbacks): void
    {
        $cliente = $this->cliente();
        $system = $this->conocimiento->systemPrompt($usuario);
        $tools = array_map(
            static fn ($tool) => $tool->definicion(),
            CatalogoTools::paraUsuario($usuario),
        );

        $maxIteraciones = (int) config('ia.max_iteraciones_tools', 8);

        for ($i = 0; $i < $maxIteraciones; $i++) {
            $params = [
                'model' => (string) config('ia.modelo'),
                'max_tokens' => (int) config('ia.max_tokens', 4096),
                'messages' => array_merge(
                    [['role' => 'system', 'content' => $system]],
                    $this->conversacion->mensajes(),
                ),
            ];

            if ($tools !== []) {
                $params['tools'] = $tools;
                $params['tool_choice'] = 'auto';
            }

            $stream = $cliente->chat()->createStreamed($params);

            $contenido = '';
            $toolCalls = [];   // acumulados por índice: ['id' => , 'name' => , 'arguments' => ]
            $finishReason = null;

            foreach ($stream as $respuesta) {
                $choice = $respuesta->choices[0] ?? null;
                if ($choice === null) {
                    continue;
                }

                $delta = $choice->delta;

                if ($delta->content !== null && $delta->content !== '') {
                    $contenido .= $delta->content;
                    $this->invocar($callbacks, 'texto', $delta->content);
                }

                foreach ($delta->toolCalls as $tc) {
                    $idx = $tc->index ?? 0;
                    if (! isset($toolCalls[$idx])) {
                        $toolCalls[$idx] = ['id' => null, 'name' => '', 'arguments' => ''];
                    }
                    if ($tc->id !== null) {
                        $toolCalls[$idx]['id'] = $tc->id;
                    }
                    if ($tc->function->name !== null) {
                        $toolCalls[$idx]['name'] = $tc->function->name;
                    }
                    $toolCalls[$idx]['arguments'] .= $tc->function->arguments;
                }

                if ($choice->finishReason !== null) {
                    $finishReason = $choice->finishReason;
                }
            }

            $this->conversacion->agregarCrudo($this->mensajeAsistente($contenido, $toolCalls));

            if ($finishReason !== 'tool_calls' || $toolCalls === []) {
                return;
            }

            foreach ($toolCalls as $tc) {
                $resultado = $this->ejecutarTool(
                    $tc['id'] ?? '',
                    $tc['name'],
                    $this->parsearArgumentos($tc['arguments']),
                    $usuario,
                    $callbacks,
                );

                $this->conversacion->agregarMensajeTool($tc['id'] ?? '', $resultado);
            }
        }
    }

    /**
     * @param  array<int, array{id: ?string, name: string, arguments: string}>  $toolCalls
     * @return array<string, mixed>
     */
    private function mensajeAsistente(string $contenido, array $toolCalls): array
    {
        if ($toolCalls === []) {
            return ['role' => 'assistant', 'content' => $contenido];
        }

        return [
            'role' => 'assistant',
            'content' => $contenido !== '' ? $contenido : null,
            'tool_calls' => array_values(array_map(fn (array $tc) => [
                'id' => $tc['id'],
                'type' => 'function',
                'function' => ['name' => $tc['name'], 'arguments' => $tc['arguments'] ?: '{}'],
            ], $toolCalls)),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function parsearArgumentos(string $arguments): array
    {
        if (trim($arguments) === '') {
            return [];
        }

        $decoded = json_decode($arguments, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  Callbacks  $callbacks
     */
    private function ejecutarTool(string $id, string $nombre, array $input, User $usuario, array $callbacks): string
    {
        $tool = CatalogoTools::resolver($nombre, $usuario);

        if ($tool === null) {
            return 'No tenés permiso para usar esta herramienta o no existe.';
        }

        if ($tool->esLectura()) {
            $this->invocar($callbacks, 'actividad', $tool->nombre());

            try {
                return (string) json_encode($tool->ejecutar($input), JSON_UNESCAPED_UNICODE);
            } catch (\Throwable $e) {
                return 'No se pudo completar la consulta: '.$e->getMessage();
            }
        }

        // Escritura: dos fases con confirmación (D4). Solo una pendiente por turno.
        if ($this->conversacion->hayAccionPendiente()) {
            return 'Ya hay una acción pendiente de confirmación del usuario. Esperá a que confirme o cancele antes de proponer otra.';
        }

        try {
            $propuesta = $tool->proponer($input);
        } catch (\Throwable $e) {
            return 'No se pudo preparar la acción: '.$e->getMessage();
        }

        $idAccion = $this->conversacion->proponerAccion(
            $tool->nombre(),
            $propuesta['parametros'],
            $propuesta['resumen'],
            $propuesta['url'] ?? null,
        );

        $this->invocar($callbacks, 'accionPendiente', [
            'id' => $idAccion,
            'tool' => $tool->nombre(),
            'resumen' => $propuesta['resumen'],
        ]);

        return 'Propuesta presentada al usuario, pendiente de su confirmación explícita.';
    }

    private function cliente(): Client
    {
        return \OpenAI::client(IaTenant::apiKey());
    }

    /**
     * @param  Callbacks  $callbacks
     */
    private function invocar(array $callbacks, string $evento, mixed $dato): void
    {
        if (isset($callbacks[$evento]) && is_callable($callbacks[$evento])) {
            ($callbacks[$evento])($dato);
        }
    }

    /**
     * @param  Callbacks  $callbacks
     */
    private function error(array $callbacks, string $codigo, string $mensaje, User $usuario, \Throwable $e): void
    {
        report($e);

        // El detalle técnico solo se expone a quien puede ver la configuración (FR-011).
        $detalle = $usuario->can('ver-configuracion') ? $e->getMessage() : null;

        if (isset($callbacks['error']) && is_callable($callbacks['error'])) {
            ($callbacks['error'])($codigo, $mensaje, $detalle);
        }
    }
}
