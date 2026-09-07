<?php

namespace Tests\Feature\Asistente;

use App\Ia\ConocimientoAsistente;
use App\Ia\ConversacionAsistente;
use App\Models\AsistenteConversacion;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AsistenteIa;
use App\Services\CompactadorConversacion;
use App\Support\IaTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * FR-013 / SC-006: una compactación fallida nunca deja al usuario sin respuesta.
 *
 * El resumen es una mejora, no un requisito para responder. Si el proveedor está caído, devuelve
 * basura o tarda de más, el turno debe seguir adelante con el recorte simple de siempre. Una
 * conversación degradada es mucho mejor que una rota.
 */
class CompactacionFallbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_si_la_compactacion_falla_el_turno_responde_igual_y_no_pierde_el_mensaje(): void
    {
        $tenant = Tenant::factory()->create();
        IaTenant::guardarApiKey('sk-de-prueba', $tenant->id);
        $usuario = User::factory()->admin()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $this->loginAs($usuario);
        tenancy()->initialize($tenant);

        // Un hilo por encima del umbral, para que toque compactar.
        config(['ia.max_mensajes' => 4]);
        $conversacion = AsistenteConversacion::create([
            'tenant_id' => $tenant->id,
            'user_id' => $usuario->id,
            'titulo' => 'Hilo largo',
            'ultima_actividad_en' => now(),
        ]);
        foreach (range(1, 20) as $i) {
            $conversacion->mensajes()->create([
                'tenant_id' => $tenant->id,
                'rol' => 'user',
                'contenido' => "mensaje {$i}",
            ]);
        }

        $conversacionServicio = app(ConversacionAsistente::class);
        $conversacionServicio->activar($conversacion);

        // Compactador que siempre revienta al pedir el resumen.
        $compactador = new class extends CompactadorConversacion
        {
            protected function pedirAlProveedor(string $entrada): string
            {
                throw new \RuntimeException('El servicio de IA no responde.');
            }
        };

        $asistente = new class($conversacionServicio, app(ConocimientoAsistente::class), $compactador) extends AsistenteIa
        {
            protected function abrirStream(array $params): iterable
            {
                return [
                    (object) ['choices' => [(object) [
                        'delta' => (object) ['content' => 'Respuesta pese al fallo.', 'toolCalls' => []],
                        'finishReason' => null,
                    ]]],
                    (object) ['choices' => [(object) [
                        'delta' => (object) ['content' => null, 'toolCalls' => []],
                        'finishReason' => 'stop',
                    ]]],
                ];
            }
        };

        $textos = [];
        $errores = [];

        $asistente->responder($usuario, 'Mi pregunta importante', [
            'texto' => function (string $d) use (&$textos) {
                $textos[] = $d;
            },
            'error' => function (string $c, string $m) use (&$errores) {
                $errores[] = $m;
            },
        ]);

        // Respondió igual...
        $this->assertSame('Respuesta pese al fallo.', implode('', $textos));
        // ...sin mostrarle al usuario un error por algo que el sistema resuelve solo...
        $this->assertSame([], $errores);
        // ...y sin perder el mensaje que acababa de enviar.
        $this->assertDatabaseHas('asistente_mensajes', [
            'conversacion_id' => $conversacion->id,
            'contenido' => 'Mi pregunta importante',
        ]);
        // La conversación sigue sin resumen: se recurrió al recorte simple.
        $this->assertNull($conversacion->fresh()->resumen);
    }

    public function test_una_compactacion_correcta_guarda_el_resumen(): void
    {
        $tenant = Tenant::factory()->create();
        IaTenant::guardarApiKey('sk-de-prueba', $tenant->id);
        $usuario = User::factory()->admin()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $this->loginAs($usuario);
        tenancy()->initialize($tenant);

        config(['ia.max_mensajes' => 4]);
        $conversacion = AsistenteConversacion::create([
            'tenant_id' => $tenant->id,
            'user_id' => $usuario->id,
            'titulo' => 'Hilo largo',
            'ultima_actividad_en' => now(),
        ]);
        foreach (range(1, 20) as $i) {
            $conversacion->mensajes()->create([
                'tenant_id' => $tenant->id,
                'rol' => 'user',
                'contenido' => "mensaje {$i}",
            ]);
        }

        $servicio = app(ConversacionAsistente::class);
        $servicio->activar($conversacion);

        $compactador = new class extends CompactadorConversacion
        {
            protected function pedirAlProveedor(string $entrada): string
            {
                return 'La persona habló de 20 cosas.';
            }
        };

        $asistente = new class($servicio, app(ConocimientoAsistente::class), $compactador) extends AsistenteIa
        {
            protected function abrirStream(array $params): iterable
            {
                return [(object) ['choices' => [(object) [
                    'delta' => (object) ['content' => 'ok', 'toolCalls' => []],
                    'finishReason' => 'stop',
                ]]]];
            }
        };

        $asistente->responder($usuario, 'Seguimos', ['texto' => fn () => null]);

        $refrescada = $conversacion->fresh();

        $this->assertSame('La persona habló de 20 cosas.', $refrescada->resumen);
        $this->assertNotNull($refrescada->resumido_hasta_mensaje_id);
    }
}
