<?php

namespace Tests\Feature\Pos;

use App\Models\PosOpcion;
use App\Models\PosOpcionGrupo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MontaSalaPos;
use Tests\TestCase;

/**
 * FR-042, SC-006: no se puede confirmar una línea sin cumplir un grupo obligatorio.
 *
 * La validación de reglas de grupo al vender vive en el `sync` de opciones del artículo (qué está
 * disponible) y se revalida al guardar la cuenta — el cliente valida en JS para dar feedback
 * inmediato, pero el servidor es quien decide.
 */
class ReglasGrupoAlVenderTest extends TestCase
{
    use MontaSalaPos, RefreshDatabase;

    private function articuloConGrupoObligatorio(): array
    {
        $grupo = PosOpcionGrupo::factory()->obligatorio()->create(['tenant_id' => $this->tenantPos->id, 'nombre' => 'Punto de cocción']);
        $opcion = PosOpcion::factory()->create(['tenant_id' => $this->tenantPos->id, 'grupo_id' => $grupo->id, 'nombre' => 'Al punto']);
        $articulo = $this->articuloPos(15.00, 10, ['nombre' => 'Solomillo']);

        $this->putJson("/articulos/{$articulo->id}/opciones", [
            'grupos' => [['grupo_id' => $grupo->id, 'orden' => 0]],
            'opciones' => [['opcion_id' => $opcion->id, 'precio' => 0, 'orden' => 0]],
        ])->assertOk();

        return [$articulo, $grupo, $opcion];
    }

    public function test_confirmar_la_linea_sin_elegir_el_grupo_obligatorio_es_rechazado(): void
    {
        $this->montarSala(['opciones_activo' => true]);
        [$articulo] = $this->articuloConGrupoObligatorio();

        $cuenta = $this->postJson('/pos/cuentas', ['mesa_id' => $this->mesasPos[1]->id])->assertCreated()->json();

        $this->putJson("/pos/cuentas/{$cuenta['id']}", [
            'version' => $cuenta['version'],
            'mesa_id' => $this->mesasPos[1]->id,
            'lineas' => [[
                'articulo_id' => $articulo->id,
                'concepto' => $articulo->nombre,
                'cantidad' => 1,
                'tipo_impositivo' => 10,
                'opciones' => [], // grupo obligatorio, sin elegir ninguna opción
            ]],
        ])->assertStatus(422);
    }

    public function test_con_la_opcion_del_grupo_obligatorio_elegida_si_se_guarda(): void
    {
        $this->montarSala(['opciones_activo' => true]);
        [$articulo, , $opcion] = $this->articuloConGrupoObligatorio();

        $this->abrirCuentaCon([
            ['articulo' => $articulo, 'cantidad' => 1, 'opciones' => [$opcion->id]],
        ]);

        $this->assertDatabaseHas('pos_cuenta_lineas', ['concepto' => 'Solomillo']);
    }
}
