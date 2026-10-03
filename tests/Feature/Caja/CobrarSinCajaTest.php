<?php

namespace Tests\Feature\Caja;

use App\Models\CajaSesion;
use App\Models\Factura;
use App\Models\PosCuenta;
use App\Models\Serie;
use App\Models\TicketPago;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MontaSalaPos;
use Tests\TestCase;

/**
 * FR-020 / FR-004 / FR-005: cobrar exige caja abierta, y todo lo cobrado queda atribuido a la
 * sesión. Test-first: un ticket fuera de toda sesión es una venta que el arqueo no vería.
 */
class CobrarSinCajaTest extends TestCase
{
    use MontaSalaPos, RefreshDatabase;

    private function payload(array $extra = []): array
    {
        return array_merge([
            'lineas' => [['concepto' => 'Café', 'cantidad' => 2, 'precio_unitario' => 1.50, 'tipo_impositivo' => 10]],
        ], $extra);
    }

    public function test_sin_caja_abierta_el_tpv_responde_409_y_no_emite_nada(): void
    {
        $this->montarSala();

        $this->postJson('/pos', $this->payload())
            ->assertStatus(409)
            ->assertJsonPath('codigo', 'caja_cerrada');

        $this->assertSame(0, Factura::withoutGlobalScopes()->count());
        $this->assertSame(0, TicketPago::withoutGlobalScopes()->count());
        // La numeración no se ha tocado: ninguna serie del tenant avanzó su próximo número.
        $this->assertSame(1, (int) Serie::withoutGlobalScopes()->where('tenant_id', $this->tenantPos->id)->max('proximo_numero'));
    }

    public function test_con_caja_abierta_cada_pago_del_ticket_queda_atribuido_a_la_sesion(): void
    {
        $this->montarSala();
        $this->postJson('/pos/caja/abrir', ['fondo_inicial' => '50'])->assertCreated();
        $sesion = CajaSesion::withoutGlobalScopes()->sole();

        // Total 3,30 € repartido en dos métodos.
        $this->postJson('/pos', $this->payload([
            'pagos' => [['metodo' => 'efectivo', 'importe' => 1.30], ['metodo' => 'tarjeta', 'importe' => 2.00]],
        ]))->assertCreated();

        $pagos = TicketPago::withoutGlobalScopes()->get();
        $this->assertCount(2, $pagos);
        $this->assertSame([$sesion->id], $pagos->pluck('caja_sesion_id')->map(fn ($id) => (int) $id)->unique()->values()->all());
    }

    public function test_cobrar_una_cuenta_de_mesa_sin_caja_responde_409_y_la_cuenta_queda_intacta(): void
    {
        $this->montarSala();
        $articulo = $this->articuloPos(10.00, 10);
        $cuenta = $this->abrirCuentaCon([['articulo' => $articulo, 'cantidad' => 2]]);

        $this->postJson("/pos/cuentas/{$cuenta['id']}/cobrar", ['version' => $cuenta['version']])
            ->assertStatus(409)
            ->assertJsonPath('codigo', 'caja_cerrada');

        $modelo = PosCuenta::withoutGlobalScopes()->with('lineas')->find($cuenta['id']);
        $this->assertSame(PosCuenta::ESTADO_ABIERTA, $modelo->estado);
        $this->assertEquals(0, (float) $modelo->lineas->sum('cantidad_saldada'));
        $this->assertSame(0, Factura::withoutGlobalScopes()->count());
    }

    public function test_cobrar_una_cuenta_con_caja_abierta_atribuye_el_ticket_a_la_sesion(): void
    {
        $this->montarSala();
        $this->postJson('/pos/caja/abrir', ['fondo_inicial' => '0'])->assertCreated();
        $articulo = $this->articuloPos(10.00, 10);
        $cuenta = $this->abrirCuentaCon([['articulo' => $articulo, 'cantidad' => 1]]);

        $this->postJson("/pos/cuentas/{$cuenta['id']}/cobrar", ['version' => $cuenta['version']])->assertCreated();

        $sesion = CajaSesion::withoutGlobalScopes()->sole();
        $this->assertSame(1, TicketPago::withoutGlobalScopes()->where('caja_sesion_id', $sesion->id)->count());
    }
}
