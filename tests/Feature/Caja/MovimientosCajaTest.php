<?php

namespace Tests\Feature\Caja;

use App\Models\CajaMovimiento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\Concerns\MontaCajaPos;
use Tests\TestCase;

/** US3 — entradas y salidas de efectivo (FR-007, FR-008). Test-first. */
class MovimientosCajaTest extends TestCase
{
    use MontaCajaPos, RefreshDatabase;

    public function test_registra_entradas_y_salidas_con_usuario_y_hora(): void
    {
        $this->montarCaja();
        $sesion = $this->abrirCajaHttp('100');

        $this->postJson('/pos/caja/movimientos', ['tipo' => 'salida', 'importe' => '30', 'motivo' => 'Pago proveedor pan'])
            ->assertCreated()
            ->assertJsonPath('message', 'Salida registrada.')
            ->assertJsonPath('movimiento.importe', '30.00')
            ->assertJsonPath('en_vivo.movimientos.0.motivo', 'Pago proveedor pan');

        $this->postJson('/pos/caja/movimientos', ['tipo' => 'entrada', 'importe' => '50', 'motivo' => 'Cambio del banco'])
            ->assertCreated()->assertJsonPath('message', 'Entrada registrada.');

        $movs = CajaMovimiento::withoutGlobalScopes()->orderBy('id')->get();
        $this->assertCount(2, $movs);
        $this->assertSame($sesion->id, (int) $movs[0]->caja_sesion_id);
        $this->assertSame($this->usuarioCaja->id, (int) $movs[0]->usuario_id);
        $this->assertSame($this->tenantCaja->id, (int) $movs[0]->tenant_id);
        $this->assertNotNull($movs[0]->created_at);
    }

    public function test_el_arqueo_cuadra_con_los_movimientos(): void
    {
        $this->montarCaja();
        $sesion = $this->abrirCajaHttp('100');
        $this->postJson('/pos/caja/movimientos', ['tipo' => 'salida', 'importe' => '30', 'motivo' => 'Pago proveedor pan'])->assertCreated();
        $this->postJson('/pos/caja/movimientos', ['tipo' => 'entrada', 'importe' => '50', 'motivo' => 'Cambio del banco'])->assertCreated();

        $this->cerrarHttp($sesion->id, ['efectivo_contado' => '120'])->assertOk()->assertJsonPath('resultado.estado', 'cuadra');
    }

    public function test_importe_no_positivo_o_sin_motivo_responde_422(): void
    {
        $this->montarCaja();
        $this->abrirCajaHttp('0');

        $this->postJson('/pos/caja/movimientos', ['tipo' => 'salida', 'importe' => '0', 'motivo' => 'X'])->assertUnprocessable();
        $this->postJson('/pos/caja/movimientos', ['tipo' => 'salida', 'importe' => '-5', 'motivo' => 'X'])->assertUnprocessable();
        $this->postJson('/pos/caja/movimientos', ['tipo' => 'salida', 'importe' => '5', 'motivo' => '  '])->assertUnprocessable();
        $this->postJson('/pos/caja/movimientos', ['tipo' => 'otro', 'importe' => '5', 'motivo' => 'X'])->assertUnprocessable();

        $this->assertSame(0, CajaMovimiento::withoutGlobalScopes()->count());
    }

    public function test_sin_caja_abierta_responde_409(): void
    {
        $this->montarCaja();

        $this->postJson('/pos/caja/movimientos', ['tipo' => 'salida', 'importe' => '5', 'motivo' => 'X'])
            ->assertStatus(409)->assertJsonPath('codigo', 'caja_cerrada');
    }

    public function test_un_movimiento_no_se_edita_ni_se_borra(): void
    {
        $this->montarCaja();
        $this->abrirCajaHttp('0');
        $this->postJson('/pos/caja/movimientos', ['tipo' => 'salida', 'importe' => '5', 'motivo' => 'Hielo'])->assertCreated();
        $mov = CajaMovimiento::withoutGlobalScopes()->sole();

        try {
            $mov->update(['importe' => 1]);
            $this->fail('Un movimiento no debería poder editarse.');
        } catch (LogicException) {
        }

        $this->expectException(LogicException::class);
        $mov->delete();
    }
}
