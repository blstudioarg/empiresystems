<?php

namespace Tests\Feature\Pos;

use App\Models\PosCuenta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MontaSalaPos;
use Tests\TestCase;

/**
 * FR-034/035/036: transferir libera la mesa origen; unir conserva todas las líneas y suma
 * importes; transferir a una mesa ocupada ofrece unir en vez de fallar en seco. **Nunca se pierde
 * consumo.**
 */
class TransferirUnirTest extends TestCase
{
    use MontaSalaPos, RefreshDatabase;

    public function test_transferir_mueve_la_cuenta_y_libera_la_mesa_origen(): void
    {
        $this->montarSala(mesas: 2);
        $articulo = $this->articuloPos(5.00, 10);

        $cuenta = $this->abrirCuentaCon([['articulo' => $articulo]], $this->mesasPos[1]);

        $this->postJson("/pos/cuentas/{$cuenta['id']}/transferir", ['mesa_id' => $this->mesasPos[2]->id])
            ->assertOk()
            ->assertJsonPath('cuenta.mesa_id', $this->mesasPos[2]->id);

        $sala = $this->getJson('/pos/sala')->json();
        $mesa1 = collect($sala['mesas'])->firstWhere('id', $this->mesasPos[1]->id);
        $mesa2 = collect($sala['mesas'])->firstWhere('id', $this->mesasPos[2]->id);

        $this->assertSame('libre', $mesa1['estado']);
        $this->assertSame('ocupada', $mesa2['estado']);
    }

    public function test_unir_conserva_todas_las_lineas_y_suma_importes(): void
    {
        $this->montarSala(mesas: 2);
        $cafe = $this->articuloPos(2.00, 10, ['nombre' => 'Café']);
        $tapa = $this->articuloPos(5.00, 10, ['nombre' => 'Tapa']);

        $origen = $this->abrirCuentaCon([['articulo' => $cafe, 'cantidad' => 2]], $this->mesasPos[1]);
        $destino = $this->abrirCuentaCon([['articulo' => $tapa]], $this->mesasPos[2]);

        $respuesta = $this->postJson("/pos/cuentas/{$origen['id']}/unir", ['cuenta_destino_id' => $destino['id']])
            ->assertOk()
            ->json('cuenta');

        // Ambas líneas viven ahora en la cuenta destino.
        $this->assertCount(2, $respuesta['lineas']);
        $conceptos = array_column($respuesta['lineas'], 'concepto');
        $this->assertContains('Café', $conceptos);
        $this->assertContains('Tapa', $conceptos);

        // 2×2,00 € + 1×5,00 € = 9,00 € + 10% = 9,90 €.
        $this->assertSame('9.90', $respuesta['pendiente']);

        // La cuenta origen queda cerrada y su mesa libre; nada se perdió.
        $this->assertSame(PosCuenta::ESTADO_CERRADA, PosCuenta::find($origen['id'])->estado);
        $sala = $this->getJson('/pos/sala')->json();
        $mesa1 = collect($sala['mesas'])->firstWhere('id', $this->mesasPos[1]->id);
        $this->assertSame('libre', $mesa1['estado']);
    }

    public function test_transferir_a_una_mesa_ocupada_no_pisa_la_cuenta_existente(): void
    {
        $this->montarSala(mesas: 2);
        $articulo = $this->articuloPos(5.00, 10);

        $origen = $this->abrirCuentaCon([['articulo' => $articulo]], $this->mesasPos[1]);
        $this->abrirCuentaCon([['articulo' => $articulo]], $this->mesasPos[2]);

        $this->postJson("/pos/cuentas/{$origen['id']}/transferir", ['mesa_id' => $this->mesasPos[2]->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors('mesa_id');

        // Nada cambió: la cuenta origen sigue en su mesa.
        $this->assertSame($this->mesasPos[1]->id, PosCuenta::find($origen['id'])->mesa_id);
    }
}
