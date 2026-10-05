<?php

namespace Tests\Feature\Traduccion;

use App\Models\Factura;
use App\Models\FacturaLinea;
use App\Models\PosPrecuenta;
use App\Support\ConfigPos;
use App\Traduccion\Bilingue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\DiccionarioFalso;
use Tests\Concerns\MontaSalaPos;
use Tests\TestCase;

/**
 * US5 / FR-019…FR-021 / Principio III (TEST-FIRST): con el POS en chino, ticket (80 mm y A4) y
 * precuenta salen bilingües (cada etiqueta en español **y** en chino), y ningún dato fiscal cambia:
 * quitando las traducciones, el documento es byte a byte el mismo que en español. Con el POS en
 * español y sin datos chinos, nada cambia; con datos chinos, la fuente pasa a Noto Sans SC.
 */
class DocumentosBilinguesTest extends TestCase
{
    use DiccionarioFalso, MontaSalaPos, RefreshDatabase;

    private function montar(string $idioma, string $concepto = 'Café con leche'): Factura
    {
        $this->montarSala();
        ConfigPos::guardar($this->tenantPos->id, ['idioma' => $idioma]);
        $this->cargarDiccionarioFalso();
        $this->postJson('/pos/caja/abrir', ['fondo_inicial' => '50'])->assertCreated();

        $id = $this->postJson('/pos', [
            'lineas' => [['concepto' => $concepto, 'cantidad' => 2, 'precio_unitario' => 1.80, 'tipo_impositivo' => 10]],
            'receptor' => ['cliente_nif' => '12345678Z', 'cliente_nombre' => 'Cliente Prueba', 'cliente_direccion' => 'Calle Mayor 1'],
        ])->assertCreated()->json('id');

        return $this->factura($id);
    }

    private function factura(int $id): Factura
    {
        return Factura::withoutGlobalScopes()->with(['lineas', 'impuestos', 'cliente', 'tenant'])->findOrFail($id);
    }

    private function html(string $vista, array $datos): string
    {
        return view($vista, $datos)->render();
    }

    /** El documento bilingüe sin las traducciones ni el bloque de fuente CJK. */
    private function sinTraducir(string $html): string
    {
        $html = preg_replace('/ \/ ⟦.*?⟧/su', '', $html);

        return preg_replace('#<style id="fuente-cjk">.*?</style>\s*#s', '', $html);
    }

    private function cambiarIdioma(string $idioma): void
    {
        ConfigPos::guardar($this->tenantPos->id, ['idioma' => $idioma]);
    }

    public function test_con_el_pos_en_chino_el_ticket_80mm_sale_bilingue(): void
    {
        $factura = $this->montar('zh');

        $html = $this->html('facturas.ticket-80mm', ['factura' => $factura, 'fuenteCjk' => Bilingue::fuenteCjkFactura($factura)]);

        $this->assertStringContainsString('Factura simplificada / ⟦Factura simplificada⟧', $html);
        $this->assertStringContainsString('TOTAL / ⟦TOTAL⟧', $html);
        $this->assertStringContainsString('Base imponible / ⟦Base imponible⟧', $html);
        $this->assertStringContainsString('Noto Sans SC', $html);
    }

    public function test_con_el_pos_en_chino_la_simplificada_a4_sale_bilingue_y_la_ordinaria_no(): void
    {
        $factura = $this->montar('zh');

        $simplificada = $this->html('facturas.pdf', ['factura' => $factura, 'fuenteCjk' => Bilingue::fuenteCjkFactura($factura)]);
        $this->assertStringContainsString('Base imponible / ⟦Base imponible⟧', $simplificada);

        $ordinaria = Factura::factory()->emitida()->create(['tenant_id' => $this->tenantPos->id]);
        FacturaLinea::factory()->for($ordinaria)->create(['tenant_id' => $this->tenantPos->id, 'concepto' => 'Servicio']);
        $ordinaria = $this->factura($ordinaria->id);

        $this->assertFalse(Bilingue::fuenteCjkFactura($ordinaria));
        $htmlOrdinaria = $this->html('facturas.pdf', ['factura' => $ordinaria, 'fuenteCjk' => false]);
        $this->assertStringNotContainsString('⟦', $htmlOrdinaria);
        $this->assertStringNotContainsString('Noto Sans SC', $htmlOrdinaria);
    }

    public function test_con_el_pos_en_chino_la_precuenta_sale_bilingue(): void
    {
        $this->montar('zh');
        $cuenta = $this->abrirCuentaCon([['articulo' => $this->articuloPos(3.00, 10)]]);
        $this->emitirPrecuenta($cuenta['id']);
        $precuenta = PosPrecuenta::withoutGlobalScopes()->with('usuario', 'tenant')->sole();

        $html = $this->html('pos.precuenta-80mm', ['precuenta' => $precuenta, 'fuenteCjk' => Bilingue::fuenteCjkPrecuenta($precuenta)]);

        $this->assertSame(2, substr_count($html, 'Documento no válido como factura / ⟦Documento no válido como factura⟧'));
        $this->assertStringContainsString('PRECUENTA / ⟦PRECUENTA⟧', $html);
        $this->assertStringContainsString('Noto Sans SC', $html);
    }

    public function test_el_idioma_no_altera_ningun_dato_fiscal_del_ticket(): void
    {
        $factura = $this->montar('es');

        $vistas = ['facturas.ticket-80mm', 'facturas.pdf'];
        $enEspanol = array_map(fn ($v) => $this->html($v, ['factura' => $factura, 'fuenteCjk' => false]), $vistas);

        $this->cambiarIdioma('zh');
        $factura = $this->factura($factura->id);
        $enChino = array_map(fn ($v) => $this->html($v, ['factura' => $factura, 'fuenteCjk' => true]), $vistas);

        foreach ($vistas as $i => $vista) {
            $this->assertNotSame($enEspanol[$i], $enChino[$i], $vista);
            // Quitadas las traducciones y la fuente, es exactamente el mismo documento: mismo
            // número, mismos importes, mismo desglose de impuestos y mismo bloque QR.
            $this->assertSame($enEspanol[$i], $this->sinTraducir($enChino[$i]), $vista);
            $this->assertStringContainsString($factura->numero_completo, $enChino[$i]);
            $this->assertStringContainsString(number_format((float) $factura->total, 2, ',', '.'), $enChino[$i]);
        }
    }

    public function test_la_precuenta_en_chino_es_la_misma_quitando_las_traducciones(): void
    {
        $this->montar('es');
        $cuenta = $this->abrirCuentaCon([['articulo' => $this->articuloPos(3.00, 10)]]);
        $this->emitirPrecuenta($cuenta['id']);
        $precuenta = PosPrecuenta::withoutGlobalScopes()->with('usuario', 'tenant')->sole();

        $enEspanol = $this->html('pos.precuenta-80mm', ['precuenta' => $precuenta, 'fuenteCjk' => false]);
        $this->cambiarIdioma('zh');
        $enChino = $this->html('pos.precuenta-80mm', ['precuenta' => $precuenta->fresh(['usuario', 'tenant']), 'fuenteCjk' => true]);

        $this->assertSame($enEspanol, $this->sinTraducir($enChino));
    }

    public function test_con_el_pos_en_espanol_y_sin_datos_chinos_nada_cambia(): void
    {
        $factura = $this->montar('es');

        $this->assertFalse(Bilingue::fuenteCjkFactura($factura));

        foreach (['facturas.ticket-80mm', 'facturas.pdf'] as $vista) {
            $html = $this->html($vista, ['factura' => $factura, 'fuenteCjk' => false]);
            $this->assertStringNotContainsString('⟦', $html);
            $this->assertStringNotContainsString(' / ', strip_tags($html));
            $this->assertStringContainsString('DejaVu Sans', $html);
            $this->assertStringNotContainsString('Noto Sans SC', $html);
        }
    }

    public function test_con_el_pos_en_espanol_un_articulo_chino_activa_la_fuente_cjk(): void
    {
        $factura = $this->montar('es', '宫保鸡丁');

        $this->assertTrue(Bilingue::fuenteCjkFactura($factura));
        $html = $this->html('facturas.ticket-80mm', ['factura' => $factura, 'fuenteCjk' => true]);

        $this->assertStringContainsString('Noto Sans SC', $html);
        $this->assertStringContainsString('宫保鸡丁', $html);
        $this->assertStringNotContainsString('⟦', $html);
    }

    public function test_los_pdf_del_ticket_y_la_precuenta_responden_con_datos_chinos(): void
    {
        $factura = $this->montar('zh', '宫保鸡丁');

        $this->get("/pos/{$factura->id}/pdf")->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->get("/pos/{$factura->id}/pdf?formato=a4")->assertOk()->assertHeader('content-type', 'application/pdf');

        $cuenta = $this->abrirCuentaCon([['articulo' => $this->articuloPos(3.00, 10, ['nombre' => '麻婆豆腐'])]]);
        $id = $this->emitirPrecuenta($cuenta['id'])['precuenta']['id'];
        $this->get("/pos/precuentas/{$id}/pdf")->assertOk()->assertHeader('content-type', 'application/pdf');
    }

    public function test_contiene_cjk_detecta_caracteres_chinos(): void
    {
        $this->assertTrue(Bilingue::contieneCjk('Café', '宫保鸡丁'));
        $this->assertFalse(Bilingue::contieneCjk('Café con leche', 'Ñandú', null));
    }
}
