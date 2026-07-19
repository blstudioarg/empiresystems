<?php

namespace Tests\Feature;

use App\Enums\VerifactuEstado;
use App\Models\Cliente;
use App\Models\Factura;
use App\Models\Serie;
use App\Models\Tenant;
use App\Models\User;
use App\Services\EmisorFacturas;
use App\Support\VerifactuTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * El PDF final (dompdf) comprime su flujo de contenido, así que buscar texto plano en el binario
 * no es fiable; se verifica el HTML que alimenta a dompdf (misma vista, mismos datos) y, aparte,
 * que la ruta HTTP responde con un PDF real (smoke test end-to-end).
 */
class VerifactuQrPdfTest extends TestCase
{
    use RefreshDatabase;

    private function tenantConVerifactu(): Tenant
    {
        $tenant = Tenant::factory()->create(['nif' => 'A58818501']);
        VerifactuTenant::activar($tenant->id, true);

        return $tenant;
    }

    private function facturaEmitidaConRegistro(Tenant $tenant): Factura
    {
        $serie = Serie::factory()->create(['tenant_id' => $tenant->id]);
        $cliente = Cliente::factory()->create([
            'tenant_id' => $tenant->id,
            'nif' => 'B12345674',
            'nombre' => 'Cliente de prueba',
            'direccion' => 'Calle Falsa 123',
        ]);

        $factura = Factura::factory()->create([
            'tenant_id' => $tenant->id,
            'serie_id' => $serie->id,
            'cliente_id' => $cliente->id,
            'cliente_nombre' => $cliente->nombre,
            'cliente_nif' => $cliente->nif,
            'cliente_direccion' => $cliente->direccion,
            'estado' => 'borrador',
            'numero' => null,
            'numero_completo' => null,
            'base_total' => 100,
            'cuota_impuesto_total' => 21,
            'total' => 121,
        ]);

        return app(EmisorFacturas::class)->emitir($factura)->load(['lineas', 'impuestos', 'cliente', 'tenant']);
    }

    public function test_el_html_del_pdf_a4_incluye_el_qr_y_veri_factu(): void
    {
        Queue::fake();

        $tenant = $this->tenantConVerifactu();
        $factura = $this->facturaEmitidaConRegistro($tenant);

        $html = view('facturas.pdf', ['factura' => $factura])->render();

        $this->assertStringContainsString('VERI*FACTU', $html);
        $this->assertStringContainsString('data:image/svg+xml;base64,', $html);
    }

    public function test_el_html_del_ticket_80mm_incluye_el_qr_y_veri_factu(): void
    {
        Queue::fake();

        $tenant = $this->tenantConVerifactu();
        $factura = $this->facturaEmitidaConRegistro($tenant);

        $html = view('facturas.ticket-80mm', ['factura' => $factura])->render();

        $this->assertStringContainsString('VERI*FACTU', $html);
        $this->assertStringContainsString('data:image/svg+xml;base64,', $html);
    }

    public function test_el_pdf_a4_se_sirve_correctamente_por_http_con_el_registro_sellado(): void
    {
        Queue::fake();

        $tenant = $this->tenantConVerifactu();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $factura = $this->facturaEmitidaConRegistro($tenant);

        $this->loginAs($user);

        $response = $this->get("/facturas/{$factura->id}/pdf");

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
    }

    public function test_una_factura_en_borrador_no_muestra_qr_ni_veri_factu(): void
    {
        $tenant = $this->tenantConVerifactu();
        $serie = Serie::factory()->create(['tenant_id' => $tenant->id]);
        $cliente = Cliente::factory()->create(['tenant_id' => $tenant->id]);

        $borrador = Factura::factory()->create([
            'tenant_id' => $tenant->id,
            'serie_id' => $serie->id,
            'cliente_id' => $cliente->id,
            'estado' => 'borrador',
            'numero' => null,
            'numero_completo' => null,
        ])->load(['lineas', 'impuestos', 'cliente', 'tenant']);

        $html = view('facturas.pdf', ['factura' => $borrador])->render();

        $this->assertStringNotContainsString('VERI*FACTU', $html);
    }

    public function test_una_factura_emitida_con_el_flag_apagado_no_muestra_qr_ni_veri_factu(): void
    {
        // Tenant SIN Verifactu activado.
        $tenant = Tenant::factory()->create();
        $serie = Serie::factory()->create(['tenant_id' => $tenant->id]);
        $cliente = Cliente::factory()->create([
            'tenant_id' => $tenant->id,
            'nif' => 'B12345674',
            'nombre' => 'Cliente de prueba',
            'direccion' => 'Calle Falsa 123',
        ]);

        $factura = Factura::factory()->create([
            'tenant_id' => $tenant->id,
            'serie_id' => $serie->id,
            'cliente_id' => $cliente->id,
            'cliente_nombre' => $cliente->nombre,
            'cliente_nif' => $cliente->nif,
            'cliente_direccion' => $cliente->direccion,
            'estado' => 'borrador',
            'numero' => null,
            'numero_completo' => null,
            'base_total' => 100,
            'total' => 121,
        ]);
        $emitida = app(EmisorFacturas::class)->emitir($factura)->load(['lineas', 'impuestos', 'cliente', 'tenant']);

        $html = view('facturas.pdf', ['factura' => $emitida])->render();

        $this->assertStringNotContainsString('VERI*FACTU', $html);
    }

    /**
     * FR-010: una factura registrada pero cuyo envío a la AEAT quedó en error muestra igualmente
     * el QR y el texto — el QR no depende del acuse de la AEAT, solo del registro local sellado.
     */
    public function test_una_factura_registrada_con_envio_en_error_muestra_igualmente_el_qr(): void
    {
        Queue::fake();

        $tenant = $this->tenantConVerifactu();
        $factura = $this->facturaEmitidaConRegistro($tenant);

        $factura->verifactu_estado = VerifactuEstado::Error;
        $factura->save();

        $html = view('facturas.pdf', ['factura' => $factura])->render();

        $this->assertStringContainsString('VERI*FACTU', $html);
    }
}
