<?php

namespace Tests\Feature\Pos;

use App\Models\PosPrecuenta;
use App\Models\PosZona;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\MontaSalaPos;
use Tests\TestCase;

/**
 * FR-007 / FR-008 / SC-004 / research D3 y D6: la precuenta se distingue sin ambigüedad de un
 * ticket (título, leyenda dos veces, sin número, serie, QR ni VERI*FACTU), nombra el impuesto del
 * régimen del tenant y se regenera siempre desde la foto guardada, no desde la cuenta viva.
 */
class PrecuentaDocumentoTest extends TestCase
{
    use MontaSalaPos, RefreshDatabase;

    private function html(PosPrecuenta $precuenta): string
    {
        return view('pos.precuenta-80mm', ['precuenta' => $precuenta->fresh(['usuario', 'tenant'])])->render();
    }

    public function test_el_pdf_responde_como_pdf(): void
    {
        $this->montarSala();
        $cuenta = $this->abrirCuentaCon([['articulo' => $this->articuloPos(3.00, 10)]]);
        $id = $this->emitirPrecuenta($cuenta['id'])['precuenta']['id'];

        $this->get("/pos/precuentas/{$id}/pdf")
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_el_documento_se_identifica_como_precuenta_y_no_lleva_nada_de_factura(): void
    {
        $this->montarSala(['suplemento_zona_activo' => true]);
        PosZona::query()->find($this->zonaPos->id)->update(['suplemento_porcentaje' => 10]);
        $cuenta = $this->abrirCuentaCon([['articulo' => $this->articuloPos(3.00, 10, ['nombre' => 'Vermut'])]]);
        $this->emitirPrecuenta($cuenta['id']);

        $html = $this->html(PosPrecuenta::query()->firstOrFail());

        $this->assertStringContainsString('PRECUENTA', $html);
        $this->assertSame(2, substr_count($html, 'Documento no válido como factura'));
        $this->assertStringContainsString('IVA incluido', $html);
        $this->assertStringContainsString('Vermut', $html);
        $this->assertStringContainsString('Mesa 1', $html);
        $this->assertStringContainsString('Suplemento Comedor', $html);
        $this->assertStringNotContainsString('VERI*FACTU', $html);
        $this->assertStringNotContainsString('verifactu-qr', $html);
        $this->assertStringNotContainsString('Factura simplificada', $html);
        $this->assertDoesNotMatchRegularExpression('/S-\d{4}-\d{4}/', $html);
        $this->assertStringNotContainsString('Reimpresión', $html);
    }

    public function test_la_hora_se_muestra_en_la_zona_horaria_del_tenant_y_no_en_utc(): void
    {
        $this->montarSala();
        $cuenta = $this->abrirCuentaCon([['articulo' => $this->articuloPos(3.00, 10)]]);

        $this->travelTo(Carbon::parse('2026-07-15 18:30:00', 'UTC'));
        $this->emitirPrecuenta($cuenta['id']);
        $this->travelBack();

        // Zona por defecto del tenant: Europe/Madrid, UTC+2 en verano.
        $this->assertStringContainsString('15/07/2026 20:30', $this->html(PosPrecuenta::query()->firstOrFail()));
    }

    public function test_bajo_igic_menciona_igic_incluido(): void
    {
        $this->montarSala(atributosTenant: ['regimen_impositivo' => 'igic']);
        $cuenta = $this->abrirCuentaCon([['articulo' => $this->articuloPos(3.00, 7)]]);
        $this->emitirPrecuenta($cuenta['id']);

        $html = $this->html(PosPrecuenta::query()->firstOrFail());

        $this->assertStringContainsString('IGIC incluido', $html);
        $this->assertStringNotContainsString('IVA incluido', $html);
    }

    public function test_la_reimpresion_lo_indica(): void
    {
        $this->montarSala();
        $cuenta = $this->abrirCuentaCon([['articulo' => $this->articuloPos(3.00, 10)]]);
        $this->emitirPrecuenta($cuenta['id']);
        $this->emitirPrecuenta($cuenta['id']);

        $html = $this->html(PosPrecuenta::query()->latest('id')->firstOrFail());

        $this->assertStringContainsString('Reimpresión', $html);
    }

    public function test_si_la_cuenta_cambia_despues_el_documento_muestra_la_foto_original(): void
    {
        $this->montarSala();
        $cuenta = $this->abrirCuentaCon([['articulo' => $this->articuloPos(3.00, 10, ['nombre' => 'Vermut'])]]);
        $this->emitirPrecuenta($cuenta['id']);

        $actual = $this->getJson("/pos/cuentas/{$cuenta['id']}")->json();
        $cafe = $this->articuloPos(1.50, 10, ['nombre' => 'Cortado']);
        $this->putJson("/pos/cuentas/{$cuenta['id']}", [
            'version' => $actual['version'],
            'mesa_id' => $actual['mesa_id'],
            'lineas' => [
                ['id' => $actual['lineas'][0]['id'], 'articulo_id' => $actual['lineas'][0]['articulo_id'], 'concepto' => 'Vermut', 'cantidad' => 1, 'tipo_impositivo' => 10, 'opciones' => []],
                ['articulo_id' => $cafe->id, 'concepto' => 'Cortado', 'cantidad' => 1, 'tipo_impositivo' => 10, 'opciones' => []],
            ],
        ])->assertOk();

        $html = $this->html(PosPrecuenta::query()->firstOrFail());

        $this->assertStringContainsString('Vermut', $html);
        $this->assertStringNotContainsString('Cortado', $html);
        $this->assertStringContainsString('3,30', $html);
    }
}
