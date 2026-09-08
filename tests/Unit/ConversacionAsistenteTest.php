<?php

namespace Tests\Unit;

use App\Ia\ConversacionAsistente;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Desde la feature 045 el hilo vive en base de datos y no en la sesión, así que estos casos
 * necesitan una persona autenticada. Las aserciones son las mismas de antes: lo que cambió es
 * dónde se guarda, no qué garantiza la clase.
 *
 * El recorte ya no borra filas —se aplica al leer, en `mensajes()`—, pero el contrato observable no
 * cambia: `truncar()` seguido de `mensajes()` devuelve la ventana correcta.
 */
class ConversacionAsistenteTest extends TestCase
{
    use RefreshDatabase;

    private function conversacion(): ConversacionAsistente
    {
        $tenant = Tenant::factory()->create();
        $usuario = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $this->loginAs($usuario);
        tenancy()->initialize($tenant);

        return new ConversacionAsistente;
    }

    public function test_truncado_preserva_pares_tool_use_tool_result_y_arranca_en_usuario(): void
    {
        config(['ia.max_mensajes' => 4]);
        $c = $this->conversacion();

        // Turno 1 (viejo) — formato OpenAI: assistant con tool_calls + mensaje tool con el resultado.
        $c->agregar('user', 'primera pregunta');
        $c->agregarCrudo(['role' => 'assistant', 'content' => null, 'tool_calls' => [['id' => 't1', 'type' => 'function', 'function' => ['name' => 'buscar', 'arguments' => '{}']]]]);
        $c->agregarMensajeTool('t1', 'ok');
        // Turno 2 (nuevo)
        $c->agregar('user', 'segunda pregunta');
        $c->agregar('assistant', 'respuesta final');

        $c->truncar();
        $mensajes = $c->mensajes();

        // Debe arrancar en un turno de usuario limpio, nunca en assistant ni tool.
        $this->assertSame('user', $mensajes[0]['role']);
        $this->assertSame('segunda pregunta', $mensajes[0]['content']);

        // Ningún mensaje `tool` debe quedar sin su assistant(tool_calls) previo (aquí, ninguno).
        $this->assertNotContains('tool', array_column($mensajes, 'role'));
    }

    public function test_una_sola_accion_pendiente_y_mensaje_nuevo_la_descarta(): void
    {
        $c = $this->conversacion();

        $id = $c->proponerAccion('crear_cliente', ['nombre' => 'X'], 'Crear cliente X');
        $this->assertTrue($c->hayAccionPendiente());
        $this->assertSame($id, $c->accionPendiente()['id']);

        // Al enviar un mensaje nuevo, el orquestador limpia la pendiente antes de agregar.
        $c->limpiarAccion();
        $c->agregar('user', 'otra cosa');
        $this->assertFalse($c->hayAccionPendiente());
    }

    public function test_reiniciar_olvida_la_conversacion_activa_y_la_accion(): void
    {
        $c = $this->conversacion();
        $c->agregar('user', 'hola');
        $c->proponerAccion('crear_cliente', [], 'resumen');

        $c->reiniciar();

        // El hilo anterior NO se borra (FR-007): simplemente deja de ser el activo.
        $this->assertSame([], $c->mensajes());
        $this->assertFalse($c->hayAccionPendiente());
        $this->assertDatabaseHas('asistente_conversaciones', ['titulo' => 'hola']);
    }

    public function test_el_titulo_sale_del_primer_mensaje_recortado(): void
    {
        $c = $this->conversacion();

        $c->agregar('user', 'Necesito saber cuántas facturas emití durante el mes de septiembre de este año');

        $conversacion = $c->activa();

        $this->assertNotNull($conversacion);
        $this->assertLessThanOrEqual(60, mb_strlen($conversacion->titulo));
        $this->assertStringStartsWith('Necesito saber cuántas facturas', $conversacion->titulo);
    }

    public function test_una_accion_propuesta_en_otra_conversacion_deja_de_ser_valida(): void
    {
        $c = $this->conversacion();

        $c->agregar('user', 'primer hilo');
        $c->proponerAccion('crear_cliente', ['nombre' => 'X'], 'Crear cliente X');
        $this->assertTrue($c->hayAccionPendiente());

        // Cambiar de hilo invalida la propuesta: nunca se ejecuta una escritura fuera de su
        // contexto (FR-023).
        $c->reiniciar();
        $c->agregar('user', 'segundo hilo');

        $this->assertFalse($c->hayAccionPendiente());
    }
}
