<?php

namespace Tests\Feature;

use App\Enums\RegimenImpositivo;
use App\Models\Cliente;
use App\Models\Factura;
use App\Models\FacturaEvento;
use App\Models\Tenant;
use App\Models\User;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FacturaeGeneracionTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'test1234';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('documentos');
    }

    private function crearTenantConCertificado(array $atributos = []): Tenant
    {
        $tenant = Tenant::factory()->create(array_merge([
            'nif' => 'A58818501',
            'razon_social' => 'Empresa Demo Test SL',
            'direccion' => 'Avenida del Sol 20',
            'cp' => '28010',
            'ciudad' => 'Madrid',
            'provincia' => 'Madrid',
            'pais' => 'ES',
        ], $atributos));

        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $this->loginAs($user);

        $archivo = UploadedFile::fake()->createWithContent(
            'certificado.p12',
            file_get_contents(base_path('tests/Fixtures/facturae/certificado.p12'))
        );

        $this->patch('/configuracion/certificado', ['certificado' => $archivo, 'password' => self::PASSWORD]);

        return $tenant;
    }

    private function verificarFirma(string $xml): bool
    {
        return \App\Support\VerificadorFirmaFacturae::esVerificable($xml);
    }

    public function test_xml_generado_valida_estructura_facturae_3_2_2_y_firma_verificable(): void
    {
        $tenant = $this->crearTenantConCertificado();
        $cliente = Cliente::factory()->create(['tenant_id' => $tenant->id, 'nif' => 'B12345674', 'pais' => 'ES']);
        $factura = Factura::factory()->emitida()->create(['tenant_id' => $tenant->id, 'cliente_id' => $cliente->id, 'cliente_nif' => 'B12345674']);
        \App\Models\FacturaLinea::factory()->create([
            'tenant_id' => $tenant->id,
            'factura_id' => $factura->id,
            'base' => 100,
            'tipo_impositivo' => 21,
            'cuota_impuesto' => 21,
        ]);

        $response = $this->get("/facturas/{$factura->id}/facturae");

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/xml');

        $xml = $response->getContent();

        $doc = new DOMDocument;
        $this->assertTrue($doc->loadXML($xml));
        $this->assertSame('fe:Facturae', $doc->documentElement->tagName);

        // Los elementos hijos no llevan prefijo (solo la raíz declara xmlns:fe): se consultan por
        // nombre de etiqueta simple, sin namespace.
        $xp = new DOMXPath($doc);
        $this->assertSame('3.2.2', $xp->query('//FileHeader/SchemaVersion')->item(0)->textContent);
        $this->assertSame(1, $xp->query('//Parties/SellerParty')->length);
        $this->assertSame(1, $xp->query('//Parties/BuyerParty')->length);
        $this->assertSame(1, $xp->query('//Invoices/Invoice')->length);

        $this->assertTrue($this->verificarFirma($xml));
    }

    public function test_importes_del_xml_coinciden_con_la_factura(): void
    {
        $tenant = $this->crearTenantConCertificado();
        $cliente = Cliente::factory()->create(['tenant_id' => $tenant->id, 'nif' => 'B12345674', 'pais' => 'ES']);
        $factura = Factura::factory()->emitida()->create([
            'tenant_id' => $tenant->id,
            'cliente_id' => $cliente->id,
            'cliente_nif' => 'B12345674',
            'base_total' => 100,
            'cuota_impuesto_total' => 21,
            'total' => 121,
        ]);
        \App\Models\FacturaLinea::factory()->create([
            'tenant_id' => $tenant->id,
            'factura_id' => $factura->id,
            'base' => 100,
            'tipo_impositivo' => 21,
            'cuota_impuesto' => 21,
        ]);

        $response = $this->get("/facturas/{$factura->id}/facturae");
        $xml = $response->getContent();

        $doc = new DOMDocument;
        $doc->loadXML($xml);
        $xp = new DOMXPath($doc);

        $total = $xp->query('//InvoiceTotals/InvoiceTotal')->item(0)->textContent;
        $this->assertSame('121.00', $total);

        $baseImponible = $xp->query('//TaxesOutputs/Tax/TaxableBase/TotalAmount')->item(0)->textContent;
        $this->assertSame('100.00', $baseImponible);
    }

    public function test_menciones_isp_y_exencion_se_reflejan_en_el_xml(): void
    {
        $tenant = $this->crearTenantConCertificado();
        $cliente = Cliente::factory()->create(['tenant_id' => $tenant->id, 'nif' => 'B12345674', 'pais' => 'ES']);
        $factura = Factura::factory()->emitida()->create(['tenant_id' => $tenant->id, 'cliente_id' => $cliente->id, 'cliente_nif' => 'B12345674']);

        \App\Models\FacturaLinea::factory()->create([
            'tenant_id' => $tenant->id,
            'factura_id' => $factura->id,
            'concepto' => 'Servicio con ISP',
            'base' => 100,
            'tipo_impositivo' => 0,
            'cuota_impuesto' => 0,
            'calificacion_operacion' => 'S2',
        ]);

        \App\Models\FacturaLinea::factory()->create([
            'tenant_id' => $tenant->id,
            'factura_id' => $factura->id,
            'concepto' => 'Entrega intracomunitaria exenta',
            'base' => 200,
            'tipo_impositivo' => 0,
            'cuota_impuesto' => 0,
            'calificacion_operacion' => 'S1',
            'causa_exencion' => 'E5',
        ]);

        $response = $this->get("/facturas/{$factura->id}/facturae");
        $xml = $response->getContent();

        $doc = new DOMDocument;
        $doc->loadXML($xml);
        $xp = new DOMXPath($doc);

        // Mención ISP a nivel de factura (sin campo propio en el esquema para S2).
        $literales = $xp->query('//LegalLiterals/LegalReference');
        $this->assertGreaterThan(0, $literales->length);
        $this->assertStringContainsString('Inversión del sujeto pasivo', $literales->item(0)->textContent);

        // Línea exenta con SpecialTaxableEvent.
        $specialReasons = $xp->query('//SpecialTaxableEvent/SpecialTaxableEventReason');
        $this->assertGreaterThan(0, $specialReasons->length);
        $this->assertStringContainsString('art. 25 LIVA', $specialReasons->item(0)->textContent);
    }

    public function test_regimen_igic_se_refleja_como_igic_no_iva(): void
    {
        $tenant = $this->crearTenantConCertificado(['regimen_impositivo' => 'igic']);
        $cliente = Cliente::factory()->create(['tenant_id' => $tenant->id, 'nif' => 'B12345674', 'pais' => 'ES']);
        $factura = Factura::factory()->emitida()->create([
            'tenant_id' => $tenant->id,
            'cliente_id' => $cliente->id,
            'cliente_nif' => 'B12345674',
            'regimen_impositivo' => RegimenImpositivo::Igic,
        ]);
        \App\Models\FacturaLinea::factory()->create([
            'tenant_id' => $tenant->id,
            'factura_id' => $factura->id,
            'base' => 100,
            'tipo_impositivo' => 7,
            'cuota_impuesto' => 7,
        ]);

        $response = $this->get("/facturas/{$factura->id}/facturae");
        $xml = $response->getContent();

        $doc = new DOMDocument;
        $doc->loadXML($xml);
        $xp = new DOMXPath($doc);

        $tipo = $xp->query('//TaxesOutputs/Tax/TaxTypeCode')->item(0)->textContent;
        $this->assertSame('03', $tipo); // Facturae::TAX_IGIC
    }

    public function test_sin_certificado_devuelve_422(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $cliente = Cliente::factory()->create(['tenant_id' => $tenant->id]);
        $factura = Factura::factory()->emitida()->create(['tenant_id' => $tenant->id, 'cliente_id' => $cliente->id]);

        $this->loginAs($user);

        $response = $this->get("/facturas/{$factura->id}/facturae");

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    public function test_nif_de_cliente_invalido_bloquea_la_generacion(): void
    {
        $tenant = $this->crearTenantConCertificado();
        $cliente = Cliente::factory()->create(['tenant_id' => $tenant->id, 'nif' => 'B12345671', 'pais' => 'ES']);
        $factura = Factura::factory()->emitida()->create([
            'tenant_id' => $tenant->id,
            'cliente_id' => $cliente->id,
            'cliente_nif' => 'B12345671', // dígito de control incorrecto (FR-021)
        ]);
        \App\Models\FacturaLinea::factory()->create(['tenant_id' => $tenant->id, 'factura_id' => $factura->id]);

        $response = $this->get("/facturas/{$factura->id}/facturae");

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    public function test_factura_no_emitida_devuelve_422(): void
    {
        $tenant = $this->crearTenantConCertificado();
        $cliente = Cliente::factory()->create(['tenant_id' => $tenant->id]);
        $factura = Factura::factory()->create(['tenant_id' => $tenant->id, 'cliente_id' => $cliente->id, 'estado' => 'borrador']);

        $response = $this->get("/facturas/{$factura->id}/facturae");

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    public function test_repetir_descarga_devuelve_el_mismo_archivo_sin_regenerar(): void
    {
        $tenant = $this->crearTenantConCertificado();
        $cliente = Cliente::factory()->create(['tenant_id' => $tenant->id, 'nif' => 'B12345674', 'pais' => 'ES']);
        $factura = Factura::factory()->emitida()->create(['tenant_id' => $tenant->id, 'cliente_id' => $cliente->id, 'cliente_nif' => 'B12345674']);
        \App\Models\FacturaLinea::factory()->create(['tenant_id' => $tenant->id, 'factura_id' => $factura->id]);

        $primera = $this->get("/facturas/{$factura->id}/facturae")->getContent();
        $segunda = $this->get("/facturas/{$factura->id}/facturae")->getContent();

        $this->assertSame($primera, $segunda);
        $this->assertSame(1, FacturaEvento::query()->where('factura_id', $factura->id)->where('tipo_evento', 'facturae_generado')->count());
    }

    public function test_aislamiento_tenant_a_no_genera_ni_descarga_el_facturae_de_b(): void
    {
        $tenantB = $this->crearTenantConCertificado();
        $clienteB = Cliente::factory()->create(['tenant_id' => $tenantB->id, 'nif' => 'B12345674', 'pais' => 'ES']);
        $facturaB = Factura::factory()->emitida()->create(['tenant_id' => $tenantB->id, 'cliente_id' => $clienteB->id, 'cliente_nif' => 'B12345674']);
        \App\Models\FacturaLinea::factory()->create(['tenant_id' => $tenantB->id, 'factura_id' => $facturaB->id]);

        $tenantA = Tenant::factory()->create();
        $userA = User::factory()->create(['tenant_id' => $tenantA->id, 'password' => bcrypt('secret123')]);
        $this->loginAs($userA);

        $response = $this->get("/facturas/{$facturaB->id}/facturae");

        $response->assertNotFound();
    }

    public function test_generar_y_enviar_adjunta_xml_y_pdf_y_registra_evento(): void
    {
        Mail::fake();

        $tenant = $this->crearTenantConCertificado();
        $cliente = Cliente::factory()->create(['tenant_id' => $tenant->id, 'nif' => 'B12345674', 'pais' => 'ES', 'email' => 'cliente@destino.test']);
        $factura = Factura::factory()->emitida()->create(['tenant_id' => $tenant->id, 'cliente_id' => $cliente->id, 'cliente_nif' => 'B12345674']);
        \App\Models\FacturaLinea::factory()->create(['tenant_id' => $tenant->id, 'factura_id' => $factura->id]);

        $this->put('/configuracion/email', [
            'smtp_host' => 'smtp.hostinger.com',
            'smtp_port' => 465,
            'smtp_encryption' => 'ssl',
            'smtp_usuario' => 'facturas@empresa.test',
            'smtp_password' => 'secreto-smtp',
            'remitente' => 'facturas@empresa.test',
            'remitente_nombre' => 'Empresa Demo',
            'responder_a' => '',
        ]);

        $response = $this->post("/facturas/{$factura->id}/facturae");

        $response->assertRedirect();
        $response->assertSessionHas('success');

        Mail::assertSent(\App\Mail\FacturaMail::class, function (\App\Mail\FacturaMail $mail) {
            return count($mail->build()->rawAttachments) === 2;
        });

        $this->assertDatabaseHas('factura_eventos', [
            'tenant_id' => $tenant->id,
            'factura_id' => $factura->id,
            'tipo_evento' => 'envio_facturae',
        ]);
    }

    public function test_cliente_sin_email_no_rompe_y_conserva_el_xml_con_aviso(): void
    {
        Mail::fake();

        $tenant = $this->crearTenantConCertificado();
        $cliente = Cliente::factory()->create(['tenant_id' => $tenant->id, 'nif' => 'B12345674', 'pais' => 'ES', 'email' => null]);
        $factura = Factura::factory()->emitida()->create(['tenant_id' => $tenant->id, 'cliente_id' => $cliente->id, 'cliente_nif' => 'B12345674']);
        \App\Models\FacturaLinea::factory()->create(['tenant_id' => $tenant->id, 'factura_id' => $factura->id]);

        $response = $this->post("/facturas/{$factura->id}/facturae");

        $response->assertRedirect();
        $response->assertSessionHas('warning');
        Mail::assertNothingSent();

        $this->assertDatabaseHas('factura_eventos', [
            'tenant_id' => $tenant->id,
            'factura_id' => $factura->id,
            'tipo_evento' => 'facturae_generado',
        ]);
    }
}
