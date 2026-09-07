<?php

namespace Tests\Feature\Asistente;

use App\Ia\ConversacionAsistente;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AsistenteIa;
use App\Support\IaTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Session;
use SessionHandlerInterface;
use Tests\TestCase;

/**
 * Regresión: todo lo que el asistente escribe en la conversación ocurre DENTRO de la closure de la
 * `StreamedResponse`, que corre en `send()` — es decir DESPUÉS de que `StartSession` ya llamó a
 * `saveSession()`. Sin un guardado explícito al cerrar el stream esos turnos (y la acción
 * pendiente) nunca llegan al almacenamiento: cada mensaje arranca sin contexto y el endpoint de
 * confirmación responde "La acción ya no está disponible".
 *
 * El oráculo es un handler espía, no la tabla `sessions`: lo que hay que comprobar es que **se
 * persiste una escritura que ya incluye la conversación**, y eso es exactamente lo que el handler
 * ve. Asertar sobre el estado en memoria no sirve —el driver `array` de `phpunit.xml` reutiliza la
 * misma instancia de `Store` entre requests y los turnos sobreviven aunque no se guarden nunca, que
 * es la razón por la que este fallo pasó desapercibido a los demás tests del asistente.
 */
class PersistenciaConversacionTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<int, string> Payloads escritos por el handler, en orden. */
    private array $escrituras = [];

    private function preparar(): User
    {
        $this->escrituras = [];

        Session::extend('espia', fn () => new class($this->escrituras) implements SessionHandlerInterface
        {
            /** @param array<int, string> $escrituras */
            public function __construct(private array &$escrituras) {}

            public function open($path, $name): bool
            {
                return true;
            }

            public function close(): bool
            {
                return true;
            }

            public function read($id): string
            {
                return '';
            }

            public function write($id, $data): bool
            {
                $this->escrituras[] = $data;

                return true;
            }

            public function destroy($id): bool
            {
                return true;
            }

            public function gc($lifetime): int
            {
                return 0;
            }
        });

        config(['session.driver' => 'espia']);

        $tenant = Tenant::factory()->create();
        $user = User::factory()->admin()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        IaTenant::guardarApiKey('sk-test-x', $tenant->id);
        $this->loginAs($user);

        return $user;
    }

    /**
     * Sustituye al orquestador real: escribe en la conversación exactamente como lo hace
     * `AsistenteIa`, pero sin red ni clave de API.
     */
    private function fakeAsistente(?string $proponer = null): void
    {
        $this->instance(AsistenteIa::class, new class(app(ConversacionAsistente::class), $proponer) extends AsistenteIa
        {
            public function __construct(
                private readonly ConversacionAsistente $conv,
                private readonly ?string $proponer,
            ) {}

            public function responder(User $usuario, string $mensaje, array $callbacks): void
            {
                $this->conv->agregar('user', $mensaje);
                $this->conv->agregarCrudo(['role' => 'assistant', 'content' => 'Respuesta de prueba.']);

                if ($this->proponer !== null) {
                    $id = $this->conv->proponerAccion(
                        'crear_cliente',
                        ['tipo' => 'particular', 'nombre' => $this->proponer],
                        'Crear cliente '.$this->proponer,
                    );
                    ($callbacks['accionPendiente'])(['id' => $id, 'tool' => 'crear_cliente', 'resumen' => 'x']);
                }
            }
        });
    }

    private function ultimaEscritura(): string
    {
        $this->assertNotEmpty($this->escrituras, 'La sesión no se guardó ni una vez.');

        return $this->escrituras[array_key_last($this->escrituras)];
    }

    public function test_los_turnos_del_stream_llegan_al_almacenamiento(): void
    {
        $this->preparar();
        $this->fakeAsistente();

        $this->post('/asistente/mensaje', ['mensaje' => 'Me llamo Federico'])->streamedContent();

        // Desde la feature 045 los turnos van a base de datos, no a la sesión: el oráculo del
        // handler espía sigue valiendo para la acción pendiente (que sí es de sesión), pero el
        // hilo se comprueba donde ahora vive.
        $this->assertDatabaseHas('asistente_mensajes', [
            'rol' => 'user',
            'contenido' => 'Me llamo Federico',
        ]);
    }

    public function test_la_accion_propuesta_en_el_stream_llega_al_almacenamiento(): void
    {
        $this->preparar();
        $this->fakeAsistente(proponer: 'Textiles Sur');

        $this->post('/asistente/mensaje', ['mensaje' => 'Creá el cliente Textiles Sur'])->streamedContent();

        $this->assertStringContainsString(
            'accion_pendiente',
            $this->ultimaEscritura(),
            'La acción pendiente no se persistió: el endpoint de confirmación no la encontrará.',
        );
    }
}
