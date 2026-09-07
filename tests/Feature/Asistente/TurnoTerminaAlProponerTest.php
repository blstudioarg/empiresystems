<?php

namespace Tests\Feature\Asistente;

use App\Ia\ConocimientoAsistente;
use App\Ia\ConversacionAsistente;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AsistenteIa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Regresión: proponer una escritura cierra el turno.
 *
 * `ejecutarTool` devuelve "Propuesta presentada al usuario, pendiente de su confirmación" y el
 * `for` del loop seguía a la iteración siguiente, así que el modelo volvía a hablar sobre lo que
 * acababa de proponer: el usuario veía la misma pregunta dos veces, la segunda ya sin tarjeta
 * (el guard de acción pendiente frena el segundo `proponer`, pero el texto ya se emitió).
 *
 * El doble sustituye `abrirStream`, el único punto del loop que toca la red.
 */
class TurnoTerminaAlProponerTest extends TestCase
{
    use RefreshDatabase;

    public function test_tras_proponer_una_escritura_no_hay_segunda_vuelta_del_modelo(): void
    {
        $tenant = Tenant::factory()->create();
        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->getTenantKey());
        tenancy()->initialize($tenant);

        $usuario = User::factory()->admin()->create(['tenant_id' => $tenant->id]);

        $asistente = new class(app(ConversacionAsistente::class), app(ConocimientoAsistente::class)) extends AsistenteIa
        {
            public int $vueltas = 0;

            protected function abrirStream(array $params): iterable
            {
                $this->vueltas++;

                // 1ª vuelta: el modelo pide crear un cliente. 2ª vuelta (que no debería ocurrir):
                // volvería a redactar la propuesta en texto plano, que es justo lo que se veía duplicado.
                return $this->vueltas === 1
                    ? [$this->trozoToolCall(), $this->trozoFin('tool_calls')]
                    : [$this->trozoTexto('He preparado la creación del cliente…'), $this->trozoFin('stop')];
            }

            private function trozoToolCall(): object
            {
                return $this->trozo((object) [
                    'content' => null,
                    'toolCalls' => [(object) [
                        'index' => 0,
                        'id' => 'call_1',
                        'function' => (object) ['name' => 'crear_cliente', 'arguments' => '{"tipo":"particular","nombre":"Empresa de Prueba S.L."}'],
                    ]],
                ], null);
            }

            private function trozoTexto(string $texto): object
            {
                return $this->trozo((object) ['content' => $texto, 'toolCalls' => []], null);
            }

            private function trozoFin(string $razon): object
            {
                return $this->trozo((object) ['content' => null, 'toolCalls' => []], $razon);
            }

            private function trozo(object $delta, ?string $finishReason): object
            {
                return (object) ['choices' => [(object) ['delta' => $delta, 'finishReason' => $finishReason]]];
            }
        };

        $textos = [];
        $acciones = [];

        $asistente->responder($usuario, 'Creá un cliente de prueba', [
            'texto' => function (string $d) use (&$textos) {
                $textos[] = $d;
            },
            'accionPendiente' => function (array $a) use (&$acciones) {
                $acciones[] = $a;
            },
            'error' => function (string $c, string $m, ?string $d) {
                $this->fail("El asistente reporto error: $c / $m / $d");
            },
        ]);

        $this->assertSame(1, $asistente->vueltas, 'El loop siguió pidiéndole otra respuesta al modelo tras proponer la escritura.');
        $this->assertCount(1, $acciones, 'Se propuso más de una acción en el mismo turno.');
        $this->assertSame([], $textos, 'El modelo volvió a redactar la propuesta: el usuario ve la pregunta dos veces.');
        $this->assertDatabaseMissing('clientes', ['nombre' => 'Empresa de Prueba S.L.']);
    }
}
