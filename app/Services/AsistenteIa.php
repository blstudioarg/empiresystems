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
    /** Tope de acciones en una sola propuesta: una lista más larga deja de ser revisable. */
    private const MAX_ACCIONES_POR_PROPUESTA = 20;

    /** @var array<int, array<string, mixed>> Escrituras preparadas en el turno en curso. */
    private array $propuestasDelTurno = [];

    public function __construct(
        private readonly ConversacionAsistente $conversacion,
        private readonly ConocimientoAsistente $conocimiento,
        private readonly ?CompactadorConversacion $compactador = null,
    ) {}

    /**
     * @param  Callbacks  $callbacks
     */
    public function responder(User $usuario, string $mensaje, array $callbacks): void
    {
        // Un mensaje nuevo descarta cualquier propuesta de escritura previa.
        $this->conversacion->limpiarAccion();
        $this->propuestasDelTurno = [];
        $this->conversacion->agregar('user', $mensaje);

        $this->compactarSiHaceFalta($callbacks);

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
     * Resume los turnos viejos si el hilo cruzó el umbral (feature 045, US2).
     *
     * Nunca puede tumbar el turno: si el resumen falla —proveedor caído, respuesta vacía, lo que
     * sea— se sigue adelante con el recorte simple de siempre, que es lo que `mensajes()` ya aplica
     * al leer. El usuario recibe su respuesta igual y no pierde el mensaje que acaba de enviar
     * (FR-013): una conversación degradada es mucho mejor que una rota.
     *
     * @param  Callbacks  $callbacks
     */
    private function compactarSiHaceFalta(array $callbacks): void
    {
        $compactador = $this->compactador;
        $conversacion = $this->conversacion->activa();

        if ($compactador === null || $conversacion === null) {
            return;
        }

        try {
            if (! $compactador->debeCompactar($conversacion)) {
                return;
            }

            $this->invocar($callbacks, 'compactando', null);

            $resultado = $compactador->compactar($conversacion);

            if ($resultado !== null) {
                $this->conversacion->aplicarCompactacion($resultado['resumen'], $resultado['hasta_mensaje_id']);
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * @param  Callbacks  $callbacks
     */
    private function ejecutarLoop(User $usuario, array $callbacks): void
    {
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

            $stream = $this->abrirStream($params);

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

            // Una propuesta de escritura cierra el turno: la pelota pasa al usuario, que confirma o
            // cancela en un request aparte (D4). Sin este corte el loop seguía iterando, el modelo
            // volvía a hablar sobre lo que acababa de proponer y el usuario veía la misma pregunta
            // dos veces (la segunda ya sin tarjeta, porque el guard de propuesta pendiente la frena).
            if ($this->presentarPropuestas($callbacks) || $this->conversacion->hayAccionPendiente()) {
                return;
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
    /**
     * Presenta como UNA tarjeta todas las escrituras preparadas en este turno. Devuelve `true` si
     * había algo que presentar, en cuyo caso el turno termina y espera al usuario.
     *
     * @param  Callbacks  $callbacks
     */
    private function presentarPropuestas(array $callbacks): bool
    {
        if ($this->propuestasDelTurno === []) {
            return false;
        }

        $acciones = $this->propuestasDelTurno;
        $this->propuestasDelTurno = [];

        $idAccion = $this->conversacion->proponerAcciones($acciones);

        $this->invocar($callbacks, 'accionPendiente', [
            'id' => $idAccion,
            'resumenes' => array_column($acciones, 'resumen'),
            // Los campos completos viajan para que el usuario pueda revisarlos antes de confirmar:
            // el resumen de una línea oculta lo que no cabe en él (diez altas con la misma razón
            // social y distinto NIF se ven idénticas si solo mirás el resumen).
            'detalle' => array_map(fn (array $a) => [
                'resumen' => $a['resumen'],
                'campos' => self::camposLegibles($a['parametros']),
            ], $acciones),
        ]);

        return true;
    }

    /**
     * Convierte los parámetros de una acción en pares etiqueta/valor listos para mostrar. Se hace en
     * servidor y no en el JS para que el criterio (qué se omite, cómo se rotula) sea uno solo y
     * pueda cubrirse con tests.
     *
     * @param  array<string, mixed>  $parametros
     * @return array<int, array{etiqueta: string, valor: string}>
     */
    public static function camposLegibles(array $parametros): array
    {
        $etiquetas = [
            'tipo' => 'Tipo',
            'nombre' => 'Nombre',
            'razon_social' => 'Razón social',
            'nif' => 'NIF/CIF',
            'email' => 'Email',
            'telefono' => 'Teléfono',
            'ciudad' => 'Ciudad',
            'direccion' => 'Dirección',
            'precio' => 'Precio',
            'referencia' => 'Referencia',
            'descripcion' => 'Descripción',
            'cliente_id' => 'Cliente',
        ];

        $campos = [];

        foreach ($parametros as $clave => $valor) {
            if (is_array($valor)) {
                $valor = json_encode($valor, JSON_UNESCAPED_UNICODE);
            }

            $campos[] = [
                'etiqueta' => $etiquetas[$clave] ?? ucfirst(str_replace('_', ' ', (string) $clave)),
                // Un campo vacío se muestra como vacío, no se esconde: que falte el NIF es
                // justamente lo que el usuario necesita ver.
                'valor' => ($valor === null || $valor === '') ? '—' : (string) $valor,
            ];
        }

        return $campos;
    }

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

        // Escritura: dos fases con confirmación (D4). Las de un mismo turno se acumulan y se
        // presentan juntas en UNA tarjeta (ver `presentarPropuestas`): pedir diez altas y tener que
        // confirmar diez tarjetas no era más seguro, solo más tedioso.
        if ($this->conversacion->hayAccionPendiente()) {
            return 'Ya hay una propuesta pendiente de confirmación del usuario. Esperá a que confirme o cancele antes de proponer otra.';
        }

        if (count($this->propuestasDelTurno) >= self::MAX_ACCIONES_POR_PROPUESTA) {
            return 'Ya se prepararon '.self::MAX_ACCIONES_POR_PROPUESTA.' acciones en esta propuesta, que es el máximo. Presentá estas al usuario antes de seguir.';
        }

        try {
            $propuesta = $tool->proponer($input);
        } catch (\Throwable $e) {
            return 'No se pudo preparar la acción: '.$e->getMessage();
        }

        $this->propuestasDelTurno[] = [
            'tool' => $tool->nombre(),
            'parametros' => $propuesta['parametros'],
            'resumen' => $propuesta['resumen'],
            'url' => $propuesta['url'] ?? null,
        ];

        return 'Acción preparada y añadida a la propuesta que se le mostrará al usuario para que la confirme.';
    }

    private function cliente(): Client
    {
        return \OpenAI::client(IaTenant::apiKey());
    }

    /**
     * Abre el stream contra el proveedor. Es el único punto del loop que toca la red, y está
     * aislado a propósito: `StreamResponse` es final y `ChatContract` la impone como tipo de
     * retorno, así que no hay forma de doblar el SDK desde afuera. Sobrescribiendo esto, un test
     * puede ejercitar el loop de tool use entero sin clave de API ni red.
     *
     * @param  array<string, mixed>  $params
     * @return iterable<mixed>
     */
    protected function abrirStream(array $params): iterable
    {
        return $this->cliente()->chat()->createStreamed($params);
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
