<?php

namespace Tests\Unit;

use App\Ia\ConversacionAsistente;
use Tests\TestCase;

class ConversacionAsistenteTest extends TestCase
{
    private function conversacion(): ConversacionAsistente
    {
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

    public function test_reiniciar_limpia_mensajes_y_accion(): void
    {
        $c = $this->conversacion();
        $c->agregar('user', 'hola');
        $c->proponerAccion('crear_cliente', [], 'resumen');

        $c->reiniciar();

        $this->assertSame([], $c->mensajes());
        $this->assertFalse($c->hayAccionPendiente());
    }
}
