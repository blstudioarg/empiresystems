<?php

namespace Tests\Feature\Asistente;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sugerencias del estado vacío del panel (feature 046, US4).
 */
class SugerenciasPanelTest extends TestCase
{
    use RefreshDatabase;

    private function usuario(): User
    {
        return User::factory()->create([
            'tenant_id' => Tenant::factory()->create()->id,
            'password' => bcrypt('secret123'),
        ]);
    }

    public function test_las_sugerencias_llegan_agrupadas_por_categoria(): void
    {
        $this->loginAs($this->usuario());

        $respuesta = $this->getJson('/asistente/sugerencias');

        $respuesta->assertOk();
        $respuesta->assertJsonStructure([
            'categorias' => [['clave', 'etiqueta', 'sugerencias']],
        ]);

        $claves = array_column($respuesta->json('categorias'), 'clave');
        $this->assertContains('importar', $claves);
        $this->assertContains('consultar', $claves);
    }

    /**
     * FR-027: no se ofrece nada que la persona no pudiera ejecutar. Una sugerencia que al pulsarla
     * responde «no tenés permiso» es peor que no ofrecer nada.
     */
    public function test_no_se_ofrece_lo_que_la_persona_no_podria_ejecutar(): void
    {
        $usuario = $this->usuario();
        $this->loginAs($usuario);
        $usuario->syncRoles([]);

        $respuesta = $this->getJson('/asistente/sugerencias');
        $respuesta->assertOk();

        $textos = [];
        foreach ($respuesta->json('categorias') as $categoria) {
            $this->assertNotEmpty($categoria['sugerencias'], 'una categoría sin sugerencias no se muestra vacía');
            $textos = [...$textos, ...$categoria['sugerencias']];
        }

        $todas = implode(' | ', $textos);

        $this->assertStringNotContainsString('importar clientes', $todas);
        $this->assertStringNotContainsString('facturas emití', $todas);
        // Las que no exigen permiso sí siguen ahí: el panel nunca se queda mudo.
        $this->assertStringContainsString('¿Qué puedo pedirte?', $todas);
    }

    public function test_quien_tiene_permisos_ve_las_de_importacion(): void
    {
        $this->loginAs($this->usuario());

        $categorias = collect($this->getJson('/asistente/sugerencias')->json('categorias'));
        $importar = $categorias->firstWhere('clave', 'importar');

        $this->assertNotNull($importar);
        $this->assertContains('Necesito importar clientes desde un Excel', $importar['sugerencias']);
    }
}
