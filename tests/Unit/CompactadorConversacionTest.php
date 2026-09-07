<?php

namespace Tests\Unit;

use App\Models\AsistenteMensaje;
use App\Services\CompactadorConversacion;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * El corte de la compactación (feature 045, research D4).
 *
 * Es el cálculo donde un error sale caro y silencioso: si el corte parte un par
 * `assistant(tool_calls)` / `tool`, el proveedor rechaza el hilo y el usuario ve un error opaco a
 * mitad de conversación. Por eso se fija con tests antes de escribirlo (Principio IV).
 *
 * No toca red ni base de datos: `calcularCorte` trabaja sobre una colección en memoria.
 */
class CompactadorConversacionTest extends TestCase
{
    private int $siguienteId = 1;

    private function mensaje(string $rol, ?array $metadatos = null): AsistenteMensaje
    {
        $m = new AsistenteMensaje([
            'rol' => $rol,
            'contenido' => 'x',
            'metadatos' => $metadatos,
        ]);
        $m->id = $this->siguienteId++;

        return $m;
    }

    /**
     * @param  array<int, AsistenteMensaje>  $mensajes
     * @return Collection<int, AsistenteMensaje>
     */
    private function coleccion(array $mensajes): Collection
    {
        return collect($mensajes);
    }

    public function test_una_conversacion_corta_no_se_corta(): void
    {
        $mensajes = $this->coleccion(array_map(
            fn () => $this->mensaje(AsistenteMensaje::ROL_USER),
            range(1, CompactadorConversacion::MENSAJES_LITERALES),
        ));

        $this->assertNull(CompactadorConversacion::calcularCorte($mensajes));
    }

    public function test_deja_intactos_los_ultimos_mensajes_literales(): void
    {
        // 15 mensajes de usuario: se resumen los 5 primeros y quedan 10 literales.
        $mensajes = $this->coleccion(array_map(
            fn () => $this->mensaje(AsistenteMensaje::ROL_USER),
            range(1, 15),
        ));

        $corte = CompactadorConversacion::calcularCorte($mensajes);

        $this->assertSame(5, $corte, 'El corte debe dejar exactamente 10 mensajes literales.');
    }

    public function test_el_corte_no_deja_un_mensaje_tool_huerfano(): void
    {
        $mensajes = [];

        // 4 turnos sueltos que sí se pueden resumir.
        for ($i = 0; $i < 4; $i++) {
            $mensajes[] = $this->mensaje(AsistenteMensaje::ROL_USER);
        }

        // Un par assistant(tool_calls) + tool que cae justo en la frontera del corte.
        $assistant = $this->mensaje(AsistenteMensaje::ROL_ASSISTANT, ['tool_calls' => [['id' => 't1', 'function' => ['name' => 'buscar', 'arguments' => '{}']]]]);
        $mensajes[] = $assistant;
        $mensajes[] = $this->mensaje(AsistenteMensaje::ROL_TOOL, ['tool_call_id' => 't1']);

        // 9 turnos más para superar el umbral de literales.
        for ($i = 0; $i < 9; $i++) {
            $mensajes[] = $this->mensaje(AsistenteMensaje::ROL_USER);
        }

        $corte = CompactadorConversacion::calcularCorte($this->coleccion($mensajes));

        $this->assertNotNull($corte);
        // El corte debe quedar ANTES del assistant que pide la herramienta, nunca entre él y su tool.
        $this->assertLessThan(
            $assistant->id,
            $corte,
            'El corte partió un par assistant(tool_calls)/tool: el proveedor rechazaría el hilo.',
        );
    }

    public function test_no_corta_si_todo_lo_resumible_es_un_bloque_de_herramientas(): void
    {
        $mensajes = [$this->mensaje(AsistenteMensaje::ROL_ASSISTANT, ['tool_calls' => [['id' => 't1', 'function' => ['name' => 'x', 'arguments' => '{}']]]])];

        for ($i = 0; $i < 12; $i++) {
            $mensajes[] = $this->mensaje(AsistenteMensaje::ROL_TOOL, ['tool_call_id' => 't1']);
        }

        // Retroceder el corte lo lleva al principio: no hay nada que resumir sin romper el hilo.
        $this->assertNull(CompactadorConversacion::calcularCorte($this->coleccion($mensajes)));
    }

    public function test_el_corte_apunta_al_ultimo_mensaje_incluido_en_el_resumen(): void
    {
        $mensajes = $this->coleccion(array_map(
            fn () => $this->mensaje(AsistenteMensaje::ROL_USER),
            range(1, 12),
        ));

        $corte = CompactadorConversacion::calcularCorte($mensajes);

        // Con 12 mensajes y 10 literales, se resumen los 2 primeros: el corte es el id del segundo.
        $this->assertSame($mensajes[1]->id, $corte);
    }
}
