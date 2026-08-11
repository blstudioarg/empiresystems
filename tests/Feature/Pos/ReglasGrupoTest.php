<?php

namespace Tests\Feature\Pos;

use App\Models\PosOpcion;
use App\Models\PosOpcionGrupo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MontaSalaPos;
use Tests\TestCase;

/**
 * FR-037: un grupo obligatorio sin opciones se rechaza, y `min ≤ max` se valida.
 */
class ReglasGrupoTest extends TestCase
{
    use MontaSalaPos, RefreshDatabase;

    private function crearGrupo(array $override = []): array
    {
        return $this->postJson('/pos/opciones/grupos', array_merge([
            'nombre' => 'Punto de cocción',
            'min_selecciones' => 0,
            'max_selecciones' => null,
            'obligatorio' => 0,
            'orden' => 0,
        ], $override))->assertCreated()->json();
    }

    public function test_no_se_puede_marcar_obligatorio_un_grupo_sin_opciones(): void
    {
        $this->montarSala(['opciones_activo' => true]);

        $grupo = $this->crearGrupo();

        $this->putJson("/pos/opciones/grupos/{$grupo['id']}", [
            'nombre' => 'Punto de cocción',
            'obligatorio' => 1,
            'min_selecciones' => 1,
        ])->assertStatus(422)->assertJsonValidationErrors('obligatorio');
    }

    public function test_un_grupo_obligatorio_con_al_menos_una_opcion_si_se_puede_guardar(): void
    {
        $this->montarSala(['opciones_activo' => true]);

        $grupo = $this->crearGrupo();
        PosOpcion::factory()->create(['tenant_id' => $this->tenantPos->id, 'grupo_id' => $grupo['id']]);

        $this->putJson("/pos/opciones/grupos/{$grupo['id']}", [
            'nombre' => 'Punto de cocción',
            'obligatorio' => 1,
            'min_selecciones' => 1,
        ])->assertOk();
    }

    public function test_el_maximo_no_puede_ser_menor_que_el_minimo(): void
    {
        $this->montarSala(['opciones_activo' => true]);

        $this->postJson('/pos/opciones/grupos', [
            'nombre' => 'Guarnición',
            'min_selecciones' => 3,
            'max_selecciones' => 1,
        ])->assertStatus(422)->assertJsonValidationErrors('max_selecciones');
    }

    public function test_min_igual_a_max_es_valido(): void
    {
        $this->montarSala(['opciones_activo' => true]);

        $this->postJson('/pos/opciones/grupos', [
            'nombre' => 'Guarnición',
            'min_selecciones' => 2,
            'max_selecciones' => 2,
        ])->assertCreated();
    }

    public function test_no_se_puede_eliminar_un_grupo_en_uso(): void
    {
        $this->montarSala(['opciones_activo' => true]);

        $grupo = PosOpcionGrupo::factory()->create(['tenant_id' => $this->tenantPos->id]);
        $articulo = $this->articuloPos();

        $this->putJson("/articulos/{$articulo->id}/opciones", [
            'grupos' => [['grupo_id' => $grupo->id, 'orden' => 0]],
            'opciones' => [],
        ])->assertOk();

        $this->deleteJson("/pos/opciones/grupos/{$grupo->id}")
            ->assertStatus(422)
            ->assertJsonFragment(['message' => "No se puede eliminar «{$grupo->nombre}»: se usa en 1 artículo(s)."]);
    }
}
