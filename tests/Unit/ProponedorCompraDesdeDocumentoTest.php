<?php

namespace Tests\Unit;

use App\Enums\RegimenImpositivo;
use App\Models\Tenant;
use App\Services\ProponedorCompraDesdeDocumento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Principio III (integridad financiera) y Principio II (el impuesto no se asume IVA).
 * Ninguna de estas pruebas llama al proveedor de IA: el proponedor recibe la lectura ya hecha.
 */
class ProponedorCompraDesdeDocumentoTest extends TestCase
{
    use RefreshDatabase;

    private function tenantCon(RegimenImpositivo $regimen): Tenant
    {
        $tenant = Tenant::factory()->create(['regimen_impositivo' => $regimen]);
        tenancy()->initialize($tenant);

        return $tenant;
    }

    private function proponer(array $lectura, string $archivo = 'factura.pdf'): array
    {
        return (new ProponedorCompraDesdeDocumento)->proponer($lectura, 'token-de-prueba', $archivo);
    }

    private function lectura(array $sobrescribir = []): array
    {
        return array_merge([
            'es_documento_compra' => true,
            'emisor' => ['nombre' => 'Suministros SL', 'nif' => 'B12345678'],
            'numero_documento' => 'F-2026/1183',
            'fecha' => '2026-03-14',
            'moneda' => 'EUR',
            'lineas' => [
                ['concepto' => 'Tornillo M6', 'cantidad' => 500, 'precio_unitario' => 0.12, 'tipo_impositivo' => 21],
            ],
            'total_documento' => 72.60,
        ], $sobrescribir);
    }

    // ---------------------------------------------------------------- T013: recálculo de importes

    public function test_recalcula_base_y_cuota_por_linea_y_los_totales(): void
    {
        $this->tenantCon(RegimenImpositivo::Iva);

        $propuesta = $this->proponer($this->lectura([
            'lineas' => [
                ['concepto' => 'Tornillo M6', 'cantidad' => 500, 'precio_unitario' => 0.12, 'tipo_impositivo' => 21],
                ['concepto' => 'Portes', 'cantidad' => 1, 'precio_unitario' => 18.5, 'tipo_impositivo' => 10],
            ],
            'total_documento' => 92.95,
        ]));

        // 500 × 0.12 = 60.00 ; 21% → 12.60
        $this->assertSame(60.0, $propuesta['lineas'][0]['base']);
        $this->assertSame(12.6, $propuesta['lineas'][0]['cuota_impuesto']);

        // 1 × 18.50 = 18.50 ; 10% → 1.85
        $this->assertSame(18.5, $propuesta['lineas'][1]['base']);
        $this->assertSame(1.85, $propuesta['lineas'][1]['cuota_impuesto']);

        $this->assertSame(78.5, $propuesta['totales']['base_total']);
        $this->assertSame(14.45, $propuesta['totales']['cuota_impuesto_total']);
        $this->assertSame(92.95, $propuesta['totales']['total']);
    }

    public function test_el_total_del_documento_no_influye_en_los_importes_calculados(): void
    {
        $this->tenantCon(RegimenImpositivo::Iva);

        // El documento "dice" 999.99, pero las líneas suman 72.60. Manda el cálculo del servidor.
        $propuesta = $this->proponer($this->lectura(['total_documento' => 999.99]));

        $this->assertSame(60.0, $propuesta['totales']['base_total']);
        $this->assertSame(12.6, $propuesta['totales']['cuota_impuesto_total']);
        $this->assertSame(72.6, $propuesta['totales']['total']);
        $this->assertSame(999.99, $propuesta['total_documento'], 'Se conserva solo para que el usuario vea la discrepancia.');
    }

    public function test_avisa_cuando_los_totales_no_cuadran_con_el_documento(): void
    {
        $this->tenantCon(RegimenImpositivo::Iva);

        $propuesta = $this->proponer($this->lectura(['total_documento' => 999.99]));

        $this->assertContains('totales_no_cuadran', array_column($propuesta['avisos'], 'tipo'));
    }

    public function test_no_avisa_cuando_los_totales_cuadran(): void
    {
        $this->tenantCon(RegimenImpositivo::Iva);

        $propuesta = $this->proponer($this->lectura(['total_documento' => 72.60]));

        $this->assertNotContains('totales_no_cuadran', array_column($propuesta['avisos'], 'tipo'));
    }

    // ------------------------------------------------------------- T014: campos ilegibles

    public function test_los_campos_nulos_de_la_lectura_se_listan_como_ilegibles_con_su_ruta(): void
    {
        $this->tenantCon(RegimenImpositivo::Iva);

        $propuesta = $this->proponer($this->lectura([
            'fecha' => null,
            'numero_documento' => null,
            'lineas' => [
                ['concepto' => 'Tornillo M6', 'cantidad' => 500, 'precio_unitario' => 0.12, 'tipo_impositivo' => 21],
                ['concepto' => 'Ilegible', 'cantidad' => null, 'precio_unitario' => 3.0, 'tipo_impositivo' => null],
            ],
        ]));

        $this->assertContains('fecha', $propuesta['campos_ilegibles']);
        $this->assertContains('numero_documento', $propuesta['campos_ilegibles']);
        $this->assertContains('lineas.1.cantidad', $propuesta['campos_ilegibles']);
        $this->assertContains('lineas.1.tipo_impositivo', $propuesta['campos_ilegibles']);
        $this->assertNotContains('lineas.0.cantidad', $propuesta['campos_ilegibles']);
    }

    /**
     * Principio II: si el documento no lo indica, el campo queda vacío. Nunca 21 % por defecto.
     */
    public function test_un_tipo_impositivo_nulo_no_se_rellena_con_el_del_regimen(): void
    {
        $this->tenantCon(RegimenImpositivo::Iva);

        $propuesta = $this->proponer($this->lectura([
            'lineas' => [
                ['concepto' => 'Sin tipo', 'cantidad' => 2, 'precio_unitario' => 10.0, 'tipo_impositivo' => null],
            ],
        ]));

        $this->assertNull($propuesta['lineas'][0]['tipo_impositivo']);
        $this->assertSame(20.0, $propuesta['lineas'][0]['base']);
        $this->assertSame(0.0, $propuesta['lineas'][0]['cuota_impuesto'], 'Sin tipo no se inventa cuota.');
    }

    // ------------------------------------------------------- T015: coherencia con el régimen

    public function test_un_tipo_de_iva_en_un_tenant_de_igic_se_marca_no_coherente(): void
    {
        $this->tenantCon(RegimenImpositivo::Igic);

        $propuesta = $this->proponer($this->lectura([
            'lineas' => [
                ['concepto' => 'Leído como IVA', 'cantidad' => 1, 'precio_unitario' => 100.0, 'tipo_impositivo' => 21],
            ],
        ]));

        $this->assertFalse($propuesta['lineas'][0]['tipo_impositivo_coherente']);
        $this->assertContains('tipo_impositivo_incoherente', array_column($propuesta['avisos'], 'tipo'));
    }

    public function test_un_tipo_propio_del_igic_es_coherente_en_un_tenant_de_igic(): void
    {
        $this->tenantCon(RegimenImpositivo::Igic);

        $propuesta = $this->proponer($this->lectura([
            'lineas' => [
                ['concepto' => 'IGIC general', 'cantidad' => 1, 'precio_unitario' => 100.0, 'tipo_impositivo' => 7],
            ],
        ]));

        $this->assertTrue($propuesta['lineas'][0]['tipo_impositivo_coherente']);
        $this->assertNotContains('tipo_impositivo_incoherente', array_column($propuesta['avisos'], 'tipo'));
    }

    public function test_un_tipo_de_iva_es_coherente_en_un_tenant_de_iva(): void
    {
        $this->tenantCon(RegimenImpositivo::Iva);

        $propuesta = $this->proponer($this->lectura());

        $this->assertTrue($propuesta['lineas'][0]['tipo_impositivo_coherente']);
    }

    public function test_un_tipo_nulo_no_se_marca_como_incoherente(): void
    {
        $this->tenantCon(RegimenImpositivo::Igic);

        $propuesta = $this->proponer($this->lectura([
            'lineas' => [
                ['concepto' => 'Sin tipo', 'cantidad' => 1, 'precio_unitario' => 10.0, 'tipo_impositivo' => null],
            ],
        ]));

        // Ilegible ya está señalado por campos_ilegibles; no se duplica como incoherencia.
        $this->assertTrue($propuesta['lineas'][0]['tipo_impositivo_coherente']);
    }

    // ------------------------------------------------------------------ otros avisos

    public function test_avisa_si_la_moneda_no_es_euro(): void
    {
        $this->tenantCon(RegimenImpositivo::Iva);

        $propuesta = $this->proponer($this->lectura(['moneda' => 'USD']));

        $this->assertContains('moneda_no_eur', array_column($propuesta['avisos'], 'tipo'));
    }

    public function test_conserva_el_nombre_del_archivo_y_el_token(): void
    {
        $this->tenantCon(RegimenImpositivo::Iva);

        $propuesta = $this->proponer($this->lectura(), 'albaran-marzo.pdf');

        $this->assertSame('albaran-marzo.pdf', $propuesta['archivo_nombre']);
        $this->assertSame('token-de-prueba', $propuesta['token']);
    }
}
