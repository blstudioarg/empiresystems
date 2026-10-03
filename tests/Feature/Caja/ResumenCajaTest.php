<?php

namespace Tests\Feature\Caja;

use App\Models\CajaSesion;
use App\Services\ResumenCaja;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MontaCajaPos;
use Tests\TestCase;

/**
 * El cálculo compartido por el informe X y el Z (feature 048, D5). Test-first: son las cifras que
 * deciden si la caja cuadra (SC-003, SC-004).
 *
 * Escenario base (todo al 21 %):
 *   A: base 10 → 12,10 € en efectivo
 *   B: base 20 → 24,20 € con tarjeta
 *   C: base 30 → 36,30 € = 10,00 efectivo + 26,30 tarjeta
 * Total 72,60 €; efectivo 22,10 €; tarjeta 50,50 €; IVA 21 %: base 60,00, cuota 12,60.
 */
class ResumenCajaTest extends TestCase
{
    use MontaCajaPos, RefreshDatabase;

    private function escenario(string $fondo = '100'): CajaSesion
    {
        $this->montarCaja();
        $sesion = $this->abrirCajaHttp($fondo);
        $this->venderHttp(10);
        $this->venderHttp(20, 21, [['metodo' => 'tarjeta', 'importe' => 24.20]]);
        $this->venderHttp(30, 21, [['metodo' => 'efectivo', 'importe' => 10.00], ['metodo' => 'tarjeta', 'importe' => 26.30]]);

        return $sesion;
    }

    private function calcular(CajaSesion $sesion): array
    {
        return app(ResumenCaja::class)->calcular($sesion->fresh());
    }

    public function test_el_escenario_cuadra_al_centimo(): void
    {
        $r = $this->calcular($this->escenario('100'));

        $this->assertSame(3, $r['num_tickets']);
        $this->assertSame('72.60', $r['total_facturado']);
        $this->assertSame('24.20', $r['ticket_medio']);
        $this->assertSame('22.10', $r['efectivo_ventas']);
        $this->assertSame('100.00', $r['fondo_inicial']);
        $this->assertSame('122.10', $r['efectivo_esperado']);

        $metodos = collect($r['resumen']['por_metodo'])->keyBy('metodo');
        $this->assertSame(['efectivo', 'tarjeta', 'transferencia', 'domiciliacion'], array_column($r['resumen']['por_metodo'], 'metodo'));
        $this->assertSame('22.10', $metodos['efectivo']['importe']);
        $this->assertSame(2, $metodos['efectivo']['tickets']);
        $this->assertSame('50.50', $metodos['tarjeta']['importe']);
        $this->assertSame(2, $metodos['tarjeta']['tickets']);
        $this->assertSame('0.00', $metodos['transferencia']['importe']);
        $this->assertSame(0, $metodos['domiciliacion']['tickets']);

        // C4: la suma por métodos es el total facturado.
        $suma = array_sum(array_map(fn ($m) => (int) round($m['importe'] * 100), $r['resumen']['por_metodo']));
        $this->assertSame(7260, $suma);

        $this->assertSame([['tipo_impuesto' => 'iva', 'porcentaje' => '21.00', 'base' => '60.00', 'cuota' => '12.60']], $r['resumen']['por_impuesto']);

        $anio = now()->year;
        $this->assertSame("S-{$anio}-0001", $r['resumen']['primer_ticket']);
        $this->assertSame("S-{$anio}-0003", $r['resumen']['ultimo_ticket']);
        $this->assertSame([], $r['resumen']['anulados']);
    }

    public function test_un_ticket_anulado_no_suma_y_aparece_en_anulados(): void
    {
        $sesion = $this->escenario('0');
        $ticketD = $this->venderHttp(5); // 6,05 € efectivo
        $this->anularTicket($ticketD);

        $r = $this->calcular($sesion);

        $this->assertSame(3, $r['num_tickets']);
        $this->assertSame('72.60', $r['total_facturado']);
        $this->assertSame('22.10', $r['efectivo_ventas']);
        $this->assertCount(1, $r['resumen']['anulados']);
        $this->assertSame('6.05', $r['resumen']['anulados'][0]['total']);
    }

    public function test_tickets_de_otra_sesion_no_cuentan(): void
    {
        $sesion = $this->escenario('0');
        $this->cerrarHttp($sesion->id, ['efectivo_contado' => '22.10'])->assertOk();

        $nueva = $this->abrirCajaHttp('0');
        $this->venderHttp(100); // 121,00 € en la sesión nueva

        $this->assertSame('121.00', $this->calcular($nueva)['total_facturado']);
        $this->assertSame('72.60', (string) $sesion->fresh()->total_facturado);
    }

    public function test_el_desglose_respeta_el_regimen_igic(): void
    {
        $this->montarCaja(['regimen_impositivo' => 'igic']);
        $sesion = $this->abrirCajaHttp('0');
        $this->venderHttp(100, 7); // 107,00 €

        $r = $this->calcular($sesion);

        $this->assertSame([['tipo_impuesto' => 'igic', 'porcentaje' => '7.00', 'base' => '100.00', 'cuota' => '7.00']], $r['resumen']['por_impuesto']);
    }

    public function test_los_movimientos_entran_en_el_esperado(): void
    {
        $sesion = $this->escenario('100');
        $this->postJson('/pos/caja/movimientos', ['tipo' => 'salida', 'importe' => '30', 'motivo' => 'Pago proveedor pan'])->assertCreated();
        $this->postJson('/pos/caja/movimientos', ['tipo' => 'entrada', 'importe' => '50', 'motivo' => 'Cambio del banco'])->assertCreated();

        $r = $this->calcular($sesion);

        $this->assertSame('50.00', $r['entradas']);
        $this->assertSame('30.00', $r['salidas']);
        $this->assertSame('142.10', $r['efectivo_esperado']); // 100 + 22,10 + 50 − 30
        $this->assertCount(2, $r['resumen']['movimientos']);
    }

    public function test_el_estado_en_vivo_trae_el_informe_x_sin_el_efectivo_esperado(): void
    {
        $this->escenario('100');

        $respuesta = $this->getJson('/pos/caja')->assertOk()
            ->assertJsonPath('abierta', true)
            ->assertJsonPath('en_vivo.num_tickets', 3)
            ->assertJsonPath('en_vivo.total_vendido', '72.60')
            ->assertJsonPath('en_vivo.ticket_medio', '24.20')
            ->assertJsonPath('en_vivo.por_metodo.0.importe', '22.10');

        // Arqueo ciego (FR-010, D6): la cifra que debería haber en el cajón no viaja al cliente.
        $this->assertStringNotContainsString('efectivo_esperado', $respuesta->getContent());
        $this->assertStringNotContainsString('122.10', $respuesta->getContent());
    }
}
