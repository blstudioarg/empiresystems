<?php

namespace Tests\Feature\Pos;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MontaSalaPos;
use Tests\TestCase;

/**
 * Regresión: los campos numéricos opcionales (`suplemento_porcentaje`, `orden`,
 * `min_selecciones`, `precio_defecto`) tienen default en columna pero son NOT NULL. El
 * formulario los envía vacíos cuando el usuario no los toca (p. ej. el campo de suplemento va
 * oculto si la capacidad está apagada); Laravel convierte ese `''` en `null`
 * (`ConvertEmptyStringsToNull`) y una regla `nullable` sin normalizar deja pasar ese `null`
 * literal hasta el INSERT, violando la constraint NOT NULL (bug real: SQLSTATE[23000] al crear
 * una zona sin tocar "Suplemento").
 */
class CrudOpcionalesVaciosTest extends TestCase
{
    use MontaSalaPos, RefreshDatabase;

    public function test_crear_zona_sin_suplemento_ni_orden_no_revienta(): void
    {
        $this->montarSala();

        $this->postJson('/configuracion/pos/zonas', [
            'nombre' => 'Terraza',
            'suplemento_porcentaje' => '',
            'orden' => '',
        ])->assertCreated();

        $this->assertDatabaseHas('pos_zonas', ['nombre' => 'Terraza', 'suplemento_porcentaje' => 0, 'orden' => 0]);
    }

    public function test_crear_mesa_sin_orden_no_revienta(): void
    {
        $this->montarSala();

        $this->postJson('/configuracion/pos/mesas', [
            'zona_id' => $this->zonaPos->id,
            'nombre' => 'Mesa 9',
            'orden' => '',
        ])->assertCreated();

        $this->assertDatabaseHas('pos_mesas', ['nombre' => 'Mesa 9', 'orden' => 0]);
    }

    public function test_crear_grupo_de_opciones_sin_min_ni_orden_no_revienta(): void
    {
        $this->montarSala(['opciones_activo' => true]);

        $this->postJson('/pos/opciones/grupos', [
            'nombre' => 'Guarnición',
            'min_selecciones' => '',
            'max_selecciones' => '',
            'orden' => '',
        ])->assertCreated();

        $this->assertDatabaseHas('pos_opcion_grupos', ['nombre' => 'Guarnición', 'min_selecciones' => 0, 'orden' => 0]);
    }

    public function test_crear_opcion_sin_precio_ni_orden_no_revienta(): void
    {
        $this->montarSala(['opciones_activo' => true]);
        $grupo = \App\Models\PosOpcionGrupo::factory()->create(['tenant_id' => $this->tenantPos->id]);

        $this->postJson('/pos/opciones', [
            'grupo_id' => $grupo->id,
            'nombre' => 'Al punto',
            'precio_defecto' => '',
            'orden' => '',
        ])->assertCreated();

        $this->assertDatabaseHas('pos_opciones', ['nombre' => 'Al punto', 'precio_defecto' => 0, 'orden' => 0]);
    }
}
