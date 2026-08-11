<?php

namespace Tests\Feature\Pos;

use App\Models\PosOpcion;
use App\Models\PosOpcionGrupo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MontaSalaPos;
use Tests\TestCase;

/**
 * FR-039: el precio de una opción es propio de cada artículo. Cambiarlo en un artículo no altera
 * `precio_defecto` ni el precio en otros artículos — es lo que permite que "Extra queso" cueste
 * 1,50 € en el solomillo y 0,50 € en el bocadillo.
 */
class PrecioOpcionPorArticuloTest extends TestCase
{
    use MontaSalaPos, RefreshDatabase;

    public function test_el_precio_de_una_opcion_es_independiente_por_articulo(): void
    {
        $this->montarSala(['opciones_activo' => true]);

        $grupo = PosOpcionGrupo::factory()->create(['tenant_id' => $this->tenantPos->id, 'nombre' => 'Extras']);
        $opcion = PosOpcion::factory()->create([
            'tenant_id' => $this->tenantPos->id,
            'grupo_id' => $grupo->id,
            'nombre' => 'Extra queso',
            'precio_defecto' => 1.00,
        ]);

        $solomillo = $this->articuloPos(18.00, 10, ['nombre' => 'Solomillo']);
        $bocadillo = $this->articuloPos(4.50, 10, ['nombre' => 'Bocadillo']);

        $this->putJson("/articulos/{$solomillo->id}/opciones", [
            'grupos' => [['grupo_id' => $grupo->id, 'orden' => 0]],
            'opciones' => [['opcion_id' => $opcion->id, 'precio' => 1.50, 'orden' => 0]],
        ])->assertOk();

        $this->putJson("/articulos/{$bocadillo->id}/opciones", [
            'grupos' => [['grupo_id' => $grupo->id, 'orden' => 0]],
            'opciones' => [['opcion_id' => $opcion->id, 'precio' => 0.50, 'orden' => 0]],
        ])->assertOk();

        $solomilloJson = $this->getJson("/articulos/{$solomillo->id}/opciones")->assertOk()->json();
        $bocadilloJson = $this->getJson("/articulos/{$bocadillo->id}/opciones")->assertOk()->json();

        $this->assertSame('1.50', $solomilloJson['opciones'][0]['precio']);
        $this->assertSame('0.50', $bocadilloJson['opciones'][0]['precio']);

        // El precio por defecto de la opción no se movió ni en la propia opción ni en base de datos.
        $this->assertSame('1.00', $solomilloJson['opciones'][0]['precio_defecto']);
        $this->assertDatabaseHas('pos_opciones', ['id' => $opcion->id, 'precio_defecto' => 1.00]);
    }
}
