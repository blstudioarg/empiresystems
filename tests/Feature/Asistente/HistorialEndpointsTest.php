<?php

namespace Tests\Feature\Asistente;

use App\Ia\ConversacionAsistente;
use App\Models\AsistenteConversacion;
use App\Models\Tenant;
use App\Models\User;
use App\Support\IaTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Endpoints del historial (feature 045, contracts/endpoints.md).
 */
class HistorialEndpointsTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $usuario;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        IaTenant::guardarApiKey('sk-de-prueba', $this->tenant->id);
        $this->usuario = User::factory()->admin()->create([
            'tenant_id' => $this->tenant->id,
            'password' => bcrypt('secret123'),
        ]);
        $this->loginAs($this->usuario);
        tenancy()->initialize($this->tenant);
    }

    private function conversacionCon(string $titulo, ?string $cuando = null): AsistenteConversacion
    {
        $conversacion = AsistenteConversacion::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->usuario->id,
            'titulo' => $titulo,
            'ultima_actividad_en' => $cuando ? now()->parse($cuando) : now(),
        ]);

        $conversacion->mensajes()->create([
            'tenant_id' => $this->tenant->id,
            'rol' => 'user',
            'contenido' => 'contenido de '.$titulo,
        ]);

        return $conversacion;
    }

    // --- Listado -------------------------------------------------------------

    public function test_lista_las_conversaciones_de_la_persona_mas_reciente_primero(): void
    {
        $vieja = $this->conversacionCon('Hilo viejo');
        $vieja->forceFill(['ultima_actividad_en' => now()->subDays(3)])->save();
        $this->conversacionCon('Hilo nuevo');

        $titulos = collect($this->getJson('/asistente/conversaciones')->assertOk()->json('conversaciones'))
            ->pluck('titulo')
            ->all();

        $this->assertSame(['Hilo nuevo', 'Hilo viejo'], $titulos);
    }

    public function test_una_conversacion_sin_mensajes_del_usuario_no_se_lista(): void
    {
        // Abrir el panel y no escribir no debe dejar un hilo en blanco en el historial (FR-008).
        AsistenteConversacion::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->usuario->id,
            'titulo' => 'Conversación nueva',
            'ultima_actividad_en' => now(),
        ]);

        $this->assertSame([], $this->getJson('/asistente/conversaciones')->json('conversaciones'));
    }

    // --- Abrir ---------------------------------------------------------------

    public function test_abrir_una_conversacion_devuelve_sus_mensajes(): void
    {
        $conversacion = $this->conversacionCon('Facturas de septiembre');

        $this->getJson("/asistente/conversaciones/{$conversacion->id}")
            ->assertOk()
            ->assertJsonPath('titulo', 'Facturas de septiembre')
            ->assertJsonPath('resumida', false)
            ->assertJsonPath('mensajes.0.rol', 'user');
    }

    public function test_el_resultado_crudo_de_una_herramienta_no_viaja_al_cliente(): void
    {
        $conversacion = $this->conversacionCon('Con consulta');

        $conversacion->mensajes()->create([
            'tenant_id' => $this->tenant->id,
            'rol' => 'assistant',
            'contenido' => null,
            'metadatos' => ['tool_calls' => [['id' => 't1', 'function' => ['name' => 'buscar_facturas', 'arguments' => '{}']]]],
        ]);
        $conversacion->mensajes()->create([
            'tenant_id' => $this->tenant->id,
            'rol' => 'tool',
            'contenido' => 'DATO-SENSIBLE-QUE-NO-DEBE-SALIR',
            'metadatos' => ['tool_call_id' => 't1'],
        ]);

        $respuesta = $this->getJson("/asistente/conversaciones/{$conversacion->id}")->assertOk();

        $respuesta->assertDontSee('DATO-SENSIBLE-QUE-NO-DEBE-SALIR');
        // Sí se ve QUÉ se consultó, que es lo que el panel pinta como actividad.
        $this->assertContains('actividad', collect($respuesta->json('mensajes'))->pluck('rol')->all());
    }

    public function test_una_conversacion_compactada_se_marca_como_resumida(): void
    {
        $conversacion = $this->conversacionCon('Larga');
        $conversacion->forceFill(['resumen' => 'Resumen previo.'])->save();

        $this->getJson("/asistente/conversaciones/{$conversacion->id}")
            ->assertOk()
            ->assertJsonPath('resumida', true);
    }

    public function test_abrir_una_conversacion_inexistente_responde_404(): void
    {
        $this->getJson('/asistente/conversaciones/999999')->assertNotFound();
    }

    // --- Conversación nueva --------------------------------------------------

    public function test_crear_conversacion_no_crea_fila_y_deja_el_panel_vacio(): void
    {
        $this->conversacionCon('Hilo anterior');

        $this->postJson('/asistente/conversaciones')
            ->assertOk()
            ->assertJson(['ok' => true, 'conversacion_id' => null]);

        // El hilo anterior sigue guardado: "conversación nueva" ya no descarta nada (FR-007).
        $this->assertSame(1, AsistenteConversacion::count());
    }

    // --- Acción pendiente al cambiar de hilo (FR-023) ------------------------

    public function test_abrir_otra_conversacion_descarta_la_accion_pendiente(): void
    {
        $unHilo = $this->conversacionCon('Donde se propuso');
        $otroHilo = $this->conversacionCon('Otro hilo');

        $conversacion = app(ConversacionAsistente::class);
        $conversacion->activar($unHilo);
        $id = $conversacion->proponerAccion('crear_cliente', ['tipo' => 'particular', 'nombre' => 'X'], 'Crear cliente X');

        // Cambiar de hilo invalida la propuesta: nunca se ejecuta una escritura fuera de su contexto.
        $this->getJson("/asistente/conversaciones/{$otroHilo->id}")->assertOk();

        $this->postJson("/asistente/accion/{$id}/confirmar")->assertNotFound();
        $this->assertDatabaseMissing('clientes', ['nombre' => 'X']);
    }

    // --- Borrado -------------------------------------------------------------

    public function test_borrar_una_conversacion_propia_la_elimina_con_sus_mensajes(): void
    {
        $conversacion = $this->conversacionCon('A borrar');

        $this->deleteJson("/asistente/conversaciones/{$conversacion->id}")
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertDatabaseMissing('asistente_conversaciones', ['id' => $conversacion->id]);
        $this->assertDatabaseMissing('asistente_mensajes', ['conversacion_id' => $conversacion->id]);
    }

    public function test_borrar_la_conversacion_activa_deja_el_panel_sin_hilo(): void
    {
        $conversacion = $this->conversacionCon('La activa');

        $servicio = app(ConversacionAsistente::class);
        $servicio->activar($conversacion);

        $this->deleteJson("/asistente/conversaciones/{$conversacion->id}")->assertOk();

        $this->assertNull(app(ConversacionAsistente::class)->idActivo());
    }

    // --- Casos límite del spec ----------------------------------------------

    public function test_sin_clave_de_ia_el_historial_sigue_siendo_accesible(): void
    {
        // El panel no deja conversar sin clave, pero lo ya guardado debe poder leerse y borrarse
        // mientras no lo alcance la purga (edge case del spec).
        $conversacion = $this->conversacionCon('Guardada antes de quitar la clave');

        IaTenant::guardarApiKey(null, $this->tenant->id);

        $this->postJson('/asistente/mensaje', ['mensaje' => 'hola'])->assertStatus(409);

        $this->getJson('/asistente/conversaciones')->assertOk();
        $this->getJson("/asistente/conversaciones/{$conversacion->id}")->assertOk();
        $this->deleteJson("/asistente/conversaciones/{$conversacion->id}")->assertOk();
    }

    public function test_si_la_conversacion_activa_desaparece_el_panel_no_revienta(): void
    {
        $conversacion = $this->conversacionCon('La que se purga');

        $servicio = app(ConversacionAsistente::class);
        $servicio->activar($conversacion);

        // La purga se la lleva mientras el panel la tenía abierta.
        $conversacion->mensajes()->delete();
        $conversacion->delete();

        $this->assertNull(app(ConversacionAsistente::class)->activa());
        $this->assertSame([], app(ConversacionAsistente::class)->mensajes());
    }
}
