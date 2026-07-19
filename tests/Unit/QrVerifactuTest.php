<?php

namespace Tests\Unit;

use App\Enums\EntornoVerifactu;
use App\Models\Factura;
use App\Models\Tenant;
use App\Support\QrVerifactu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QrVerifactuTest extends TestCase
{
    use RefreshDatabase;

    private function factura(EntornoVerifactu $entorno): Factura
    {
        $tenant = Tenant::factory()->create(['nif' => 'A58818501']);

        return Factura::factory()->create([
            'tenant_id' => $tenant->id,
            'numero_completo' => '12345678/G33',
            'fecha_expedicion' => '2024-01-01',
            'total' => 123.45,
            'verifactu_entorno' => $entorno,
        ]);
    }

    public function test_la_url_de_pruebas_usa_el_dominio_de_preproduccion(): void
    {
        $url = QrVerifactu::url($this->factura(EntornoVerifactu::Pruebas));

        $this->assertStringStartsWith('https://prewww2.aeat.es/wlpl/TIKE-CONT/ValidarQR?', $url);
    }

    public function test_la_url_de_produccion_usa_el_dominio_oficial(): void
    {
        $url = QrVerifactu::url($this->factura(EntornoVerifactu::Produccion));

        $this->assertStringStartsWith('https://www2.agenciatributaria.gob.es/wlpl/TIKE-CONT/ValidarQR?', $url);
    }

    public function test_los_parametros_van_en_el_orden_y_formato_oficiales(): void
    {
        $url = QrVerifactu::url($this->factura(EntornoVerifactu::Pruebas));

        $query = parse_url($url, PHP_URL_QUERY);
        $this->assertSame(
            'nif=A58818501&numserie=12345678%2FG33&fecha=01-01-2024&importe=123.45',
            $query,
        );
    }

    public function test_genera_una_imagen_qr_como_data_uri_svg(): void
    {
        $imagen = QrVerifactu::imagen('https://prewww2.aeat.es/wlpl/TIKE-CONT/ValidarQR?nif=A58818501');

        $this->assertStringStartsWith('data:image/svg+xml;base64,', $imagen);
    }
}
