<?php

namespace Tests\Feature\Pos;

use App\Models\PosOpcion;
use App\Models\PosOpcionGrupo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MontaSalaPos;
use Tests\TestCase;

/**
 * Principio III + FR-048: los suplementos elegidos se suman al importe **en servidor**. El
 * cliente solo envía qué eligió (el `opcion_id`); nunca cuánto cuesta.
 */
class VentaConOpcionesTest extends TestCase
{
    use MontaSalaPos, RefreshDatabase;

    public function test_el_suplemento_de_la_opcion_se_calcula_en_servidor_ignorando_el_precio_del_cliente(): void
    {
        $this->montarSala(['opciones_activo' => true]);

        $grupo = PosOpcionGrupo::factory()->create(['tenant_id' => $this->tenantPos->id]);
        $opcion = PosOpcion::factory()->create([
            'tenant_id' => $this->tenantPos->id,
            'grupo_id' => $grupo->id,
            'nombre' => 'Extra queso',
            'precio_defecto' => 1.50,
        ]);

        $articulo = $this->articuloPos(10.00, 10, ['nombre' => 'Bocadillo']);
        $this->putJson("/articulos/{$articulo->id}/opciones", [
            'grupos' => [['grupo_id' => $grupo->id, 'orden' => 0]],
            'opciones' => [['opcion_id' => $opcion->id, 'precio' => 1.50, 'orden' => 0]],
        ])->assertOk();

        // El cliente manda el opcion_id; si intentara mandar un "precio" propio, se ignora: el
        // payload de guardar cuenta no acepta precio de opción, solo opcion_id (ver FormRequest).
        $cuenta = $this->abrirCuentaCon([
            ['articulo' => $articulo, 'cantidad' => 1, 'opciones' => [$opcion->id]],
        ]);

        // 10 € base + 1,50 € de suplemento = 11,50 € + 10% = 12,65 €.
        $this->assertSame('12.65', $cuenta['pendiente']);
        $this->assertSame(1.50, $cuenta['lineas'][0]['suplemento_opciones']);
    }
}
