<?php

namespace Tests\Feature\Caja;

use App\Models\CajaSesion;
use App\Support\ConfigPos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\Concerns\MontaCajaPos;
use Tests\TestCase;

/**
 * US2 — cerrar con arqueo ciego (FR-009 a FR-014, FR-017). Test-first (Principio III/IV): el
 * esperado, el contado y la diferencia los decide el servidor, y un cierre no se mueve nunca más.
 */
class CierreCajaTest extends TestCase
{
    use MontaCajaPos, RefreshDatabase;

    /** Fondo 100 + un ticket de 12,10 € en efectivo + uno de 24,20 € con tarjeta → esperado 112,10 €. */
    private function cajaConVentas(): CajaSesion
    {
        $this->montarCaja();
        $sesion = $this->abrirCajaHttp('100');
        $this->venderHttp(10);                                                   // 12,10 efectivo
        $this->venderHttp(20, 21, [['metodo' => 'tarjeta', 'importe' => 24.20]]); // 24,20 tarjeta

        return $sesion; // esperado = 100 + 12,10 = 112,10
    }

    public function test_cierra_con_conteo_y_el_contado_sale_del_mapa_de_denominaciones(): void
    {
        $sesion = $this->cajaConVentas();

        // 2 × 50 + 1 × 10 + 1 × 2 + 1 × 0,10 = 112,10 → cuadra.
        $this->cerrarHttp($sesion->id, [
            'conteo' => ['5000' => 2, '1000' => 1, '200' => 1, '10' => 1],
            'efectivo_contado' => '1.00', // mentira a propósito: manda el conteo
        ])->assertOk()
            ->assertJsonPath('resultado.efectivo_esperado', '112.10')
            ->assertJsonPath('resultado.efectivo_contado', '112.10')
            ->assertJsonPath('resultado.descuadre', '0.00')
            ->assertJsonPath('resultado.estado', 'cuadra')
            ->assertJsonPath('informe.num_tickets', 2)
            ->assertJsonPath('informe.total_facturado', '36.30');

        $cerrada = $sesion->fresh();
        $this->assertSame(CajaSesion::ESTADO_CERRADA, $cerrada->estado);
        $this->assertNull($cerrada->abierta_marca);
        $this->assertSame($this->usuarioCaja->id, (int) $cerrada->cerrada_por);
        $this->assertNotNull($cerrada->cerrada_at);
        $this->assertSame(['5000' => 2, '1000' => 1, '200' => 1, '10' => 1], $cerrada->conteo_cierre);
        $this->assertSame('36.30', (string) $cerrada->total_facturado);
        $this->assertCount(4, $cerrada->resumen['por_metodo']);
    }

    public function test_cierra_con_importe_total_y_detecta_sobra_y_falta(): void
    {
        $sesion = $this->cajaConVentas();

        $this->cerrarHttp($sesion->id, ['efectivo_contado' => '110.10'])->assertOk()
            ->assertJsonPath('resultado.descuadre', '-2.00')
            ->assertJsonPath('resultado.estado', 'falta');
    }

    public function test_un_sobrante_se_marca_como_sobra(): void
    {
        $sesion = $this->cajaConVentas();

        $this->cerrarHttp($sesion->id, ['efectivo_contado' => '113.60'])->assertOk()
            ->assertJsonPath('resultado.descuadre', '1.50')
            ->assertJsonPath('resultado.estado', 'sobra');
    }

    public function test_una_diferencia_sobre_el_umbral_exige_observacion_y_no_cierra(): void
    {
        $sesion = $this->cajaConVentas();

        $this->cerrarHttp($sesion->id, ['efectivo_contado' => '104.70'])
            ->assertStatus(422)
            ->assertJsonPath('codigo', 'observacion_requerida')
            ->assertJsonPath('resultado.descuadre', '-7.40')
            ->assertJsonPath('resultado.estado', 'falta');

        $this->assertTrue($sesion->fresh()->estaAbierta());

        $this->cerrarHttp($sesion->id, ['efectivo_contado' => '104.70', 'observacion' => 'Se pagó un café al repartidor sin registrarlo'])
            ->assertOk()
            ->assertJsonPath('informe.observacion', 'Se pagó un café al repartidor sin registrarlo');
    }

    public function test_el_umbral_configurado_por_el_negocio_se_respeta(): void
    {
        $sesion = $this->cajaConVentas();
        ConfigPos::guardar($this->tenantCaja->id, ['caja_umbral_descuadre' => 1]);

        $this->cerrarHttp($sesion->id, ['efectivo_contado' => '110.10'])
            ->assertStatus(422)->assertJsonPath('codigo', 'observacion_requerida');
    }

    public function test_el_umbral_se_guarda_desde_la_configuracion_del_pos(): void
    {
        $this->montarCaja();

        $this->putJson('/configuracion/pos', [
            'hosteleria_activo' => 0, 'opciones_activo' => 0, 'cobro_dividido_activo' => 0,
            'suplemento_zona_activo' => 0, 'mesa_olvidada_min' => 45, 'caja_umbral_descuadre' => '2.50',
        ])->assertOk();
        $this->assertSame(2.50, ConfigPos::cajaUmbralDescuadre($this->tenantCaja->id));

        $this->putJson('/configuracion/pos', [
            'hosteleria_activo' => 0, 'mesa_olvidada_min' => 45, 'caja_umbral_descuadre' => '-1',
        ])->assertUnprocessable();
    }

    public function test_si_otra_tablet_ya_la_cerro_responde_409_con_el_informe(): void
    {
        $sesion = $this->cajaConVentas();
        $this->cerrarHttp($sesion->id, ['efectivo_contado' => '112.10'])->assertOk();

        $this->cerrarHttp($sesion->id, ['efectivo_contado' => '112.10'])
            ->assertStatus(409)
            ->assertJsonPath('codigo', 'caja_ya_cerrada')
            ->assertJsonStructure(['informe_url_ticket']);
    }

    public function test_si_la_sesion_contada_ya_no_es_la_abierta_no_cierra_la_nueva(): void
    {
        $sesion = $this->cajaConVentas();
        $this->cerrarHttp($sesion->id, ['efectivo_contado' => '112.10'])->assertOk();
        $nueva = $this->abrirCajaHttp('0');

        $this->cerrarHttp($sesion->id, ['efectivo_contado' => '0'])->assertStatus(409)->assertJsonPath('codigo', 'caja_ya_cerrada');

        $this->assertTrue($nueva->fresh()->estaAbierta());
    }

    public function test_sin_caja_abierta_responde_409(): void
    {
        $this->montarCaja();

        $this->cerrarHttp(999, ['efectivo_contado' => '0'])->assertStatus(409)->assertJsonPath('codigo', 'caja_cerrada');
    }

    public function test_exige_un_conteo_o_un_total(): void
    {
        $sesion = $this->cajaConVentas();

        $this->cerrarHttp($sesion->id, [])->assertUnprocessable();
        $this->postJson('/pos/caja/cerrar', ['efectivo_contado' => '1'])->assertUnprocessable();
    }

    public function test_tras_cerrar_se_puede_abrir_una_caja_nueva(): void
    {
        $sesion = $this->cajaConVentas();
        $this->cerrarHttp($sesion->id, ['efectivo_contado' => '112.10'])->assertOk();

        $this->postJson('/pos/caja/abrir', ['fondo_inicial' => '50'])->assertCreated();
        $this->getJson('/pos/caja')->assertJsonPath('abierta', true);
    }

    public function test_una_caja_cerrada_no_se_modifica_ni_se_borra(): void
    {
        $sesion = $this->cajaConVentas();
        $this->cerrarHttp($sesion->id, ['efectivo_contado' => '112.10'])->assertOk();
        $cerrada = CajaSesion::withoutGlobalScopes()->find($sesion->id);

        try {
            $cerrada->update(['efectivo_contado' => '999']);
            $this->fail('Una caja cerrada no debería poder modificarse.');
        } catch (LogicException) {
        }

        $this->expectException(LogicException::class);
        $cerrada->delete();
    }

    public function test_anular_despues_un_ticket_no_cambia_el_cierre(): void
    {
        $this->montarCaja();
        $sesion = $this->abrirCajaHttp('0');
        $ticket = $this->venderHttp(10); // 12,10 efectivo
        $this->cerrarHttp($sesion->id, ['efectivo_contado' => '12.10'])->assertOk();
        $resumenAntes = $sesion->fresh()->resumen;

        $this->anularTicket($ticket);

        $despues = $sesion->fresh();
        $this->assertSame('12.10', (string) $despues->total_facturado);
        $this->assertSame(1, $despues->num_tickets);
        $this->assertSame($resumenAntes, $despues->resumen);
    }

    public function test_una_sesion_sin_ventas_se_cierra_contra_fondo_y_movimientos(): void
    {
        $this->montarCaja();
        $sesion = $this->abrirCajaHttp('100');
        $this->postJson('/pos/caja/movimientos', ['tipo' => 'salida', 'importe' => '30', 'motivo' => 'Pago proveedor'])->assertCreated();
        $this->postJson('/pos/caja/movimientos', ['tipo' => 'entrada', 'importe' => '50', 'motivo' => 'Cambio'])->assertCreated();

        $this->cerrarHttp($sesion->id, ['efectivo_contado' => '120'])->assertOk()
            ->assertJsonPath('resultado.estado', 'cuadra')
            ->assertJsonPath('informe.num_tickets', 0);
    }

    public function test_el_esperado_puede_ser_negativo(): void
    {
        $this->montarCaja();
        $sesion = $this->abrirCajaHttp('0');
        $this->postJson('/pos/caja/movimientos', ['tipo' => 'salida', 'importe' => '3', 'motivo' => 'Propina'])->assertCreated();

        $this->cerrarHttp($sesion->id, ['efectivo_contado' => '0'])->assertOk()
            ->assertJsonPath('resultado.efectivo_esperado', '-3.00')
            ->assertJsonPath('resultado.descuadre', '3.00');
    }

    public function test_un_ticket_emitido_justo_antes_de_confirmar_entra_en_el_cierre(): void
    {
        $this->montarCaja();
        $sesion = $this->abrirCajaHttp('0');
        $this->getJson('/pos/caja')->assertOk(); // "abre la pantalla de conteo"
        $this->venderHttp(10);                    // otra tablet vende mientras tanto

        $this->cerrarHttp($sesion->id, ['efectivo_contado' => '12.10'])->assertOk()
            ->assertJsonPath('resultado.estado', 'cuadra')
            ->assertJsonPath('informe.num_tickets', 1);
    }
}
