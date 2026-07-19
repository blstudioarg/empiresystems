<?php

namespace Tests\Feature;

use App\Enums\VerifactuEstado;
use App\Jobs\RemitirRegistroVerifactu;
use App\Models\Cliente;
use App\Models\Factura;
use App\Models\Serie;
use App\Models\Tenant;
use App\Models\User;
use App\Services\EmisorFacturas;
use App\Services\RegistroVerifactu;
use App\Support\CertificadoTenant;
use App\Support\VerifactuTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RemisionVerifactuTest extends TestCase
{
    use RefreshDatabase;

    private const CERT_PASSWORD = 'test1234';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('documentos');
        // La cola corre en modo síncrono en estos tests: se necesita ver el resultado real del
        // envío tras emitir, no interceptarlo con Queue::fake() como en el resto de la suite.
        config(['queue.default' => 'sync']);
    }

    private function tenantConVerifactuYCertificado(): Tenant
    {
        $tenant = Tenant::factory()->create(['nif' => 'A58818501']);
        VerifactuTenant::activar($tenant->id, true);

        $archivo = UploadedFile::fake()->createWithContent(
            'certificado.p12',
            file_get_contents(base_path('tests/Fixtures/facturae/certificado.p12')),
        );
        CertificadoTenant::guardar($archivo, self::CERT_PASSWORD, $tenant->id);

        return $tenant;
    }

    private function facturaEmitida(Tenant $tenant): Factura
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

        return app(EmisorFacturas::class)->emitir($factura);
    }

    public function test_una_respuesta_aceptada_deja_la_factura_enviada(): void
    {
        Http::fake([
            '*' => Http::response('<RespuestaRegFactuSistemaFacturacion><EstadoEnvio>Correcto</EstadoEnvio><CSV>CSV-OK-1</CSV><RespuestaLinea><EstadoRegistro>Correcto</EstadoRegistro></RespuestaLinea></RespuestaRegFactuSistemaFacturacion>', 200),
        ]);

        $tenant = $this->tenantConVerifactuYCertificado();
        $factura = $this->facturaEmitida($tenant);

        $this->assertSame(VerifactuEstado::Enviada, $factura->refresh()->verifactu_estado);
        $this->assertDatabaseHas('factura_eventos', [
            'factura_id' => $factura->id,
            'tipo_evento' => 'verifactu_enviado',
        ]);
    }

    public function test_un_rechazo_funcional_deja_la_factura_en_error_con_motivo(): void
    {
        Http::fake([
            '*' => Http::response('<RespuestaRegFactuSistemaFacturacion><EstadoEnvio>Incorrecto</EstadoEnvio><RespuestaLinea><EstadoRegistro>Incorrecto</EstadoRegistro><CodigoErrorRegistro>1234</CodigoErrorRegistro><DescripcionErrorRegistro>NIF no válido</DescripcionErrorRegistro></RespuestaLinea></RespuestaRegFactuSistemaFacturacion>', 200),
        ]);

        $tenant = $this->tenantConVerifactuYCertificado();
        $factura = $this->facturaEmitida($tenant);

        $factura->refresh();
        $this->assertSame(VerifactuEstado::Error, $factura->verifactu_estado);

        $evento = $factura->eventos()->where('tipo_evento', 'verifactu_error')->first();
        $this->assertNotNull($evento);
        $this->assertSame('rechazo', $evento->detalle['tipo']);
        $this->assertSame('1234', $evento->detalle['codigo']);
    }

    public function test_un_fallo_de_transporte_deja_la_factura_en_error_reintentable(): void
    {
        Http::fake(['*' => Http::response('Bad Gateway', 502)]);

        $tenant = $this->tenantConVerifactuYCertificado();
        $factura = $this->facturaEmitida($tenant);

        $factura->refresh();
        $this->assertSame(VerifactuEstado::Error, $factura->verifactu_estado);

        $evento = $factura->eventos()->where('tipo_evento', 'verifactu_error')->first();
        $this->assertSame('transporte', $evento->detalle['tipo']);
        $this->assertTrue($factura->verifactuReintentable());
    }

    public function test_un_fallo_de_la_aeat_no_bloquea_la_emision(): void
    {
        Http::fake(['*' => Http::response('Bad Gateway', 502)]);

        $tenant = $this->tenantConVerifactuYCertificado();
        $factura = $this->facturaEmitida($tenant);

        $this->assertSame('emitida', $factura->estado->value);
        $this->assertNotEmpty($factura->huella);
    }

    public function test_el_reintento_reenvia_el_mismo_registro_sin_recalcular_huella_ni_anadir_eslabon(): void
    {
        // Un único fake con contador: el primer envío (durante emitir()) falla, el segundo
        // (reintento) se acepta. Http::fake() no reemplaza stubs previos, apila y gana el primero
        // registrado que matchee — por eso un solo stub con lógica interna, no dos Http::fake().
        $intentos = 0;
        Http::fake([
            '*' => function () use (&$intentos) {
                $intentos++;

                return $intentos === 1
                    ? Http::response('Bad Gateway', 502)
                    : Http::response('<RespuestaRegFactuSistemaFacturacion><EstadoEnvio>Correcto</EstadoEnvio><CSV>CSV-RETRY</CSV><RespuestaLinea><EstadoRegistro>Correcto</EstadoRegistro></RespuestaLinea></RespuestaRegFactuSistemaFacturacion>', 200);
            },
        ]);

        $tenant = $this->tenantConVerifactuYCertificado();
        $factura = $this->facturaEmitida($tenant);
        $factura->refresh();

        $huellaOriginal = $factura->huella;
        $huellaAnteriorOriginal = $factura->huella_anterior;
        $xmlOriginal = $factura->registro_xml;
        $registradaAtOriginal = $factura->registrada_at;

        RemitirRegistroVerifactu::dispatchSync($factura->id);

        $factura->refresh();
        $this->assertSame(VerifactuEstado::Enviada, $factura->verifactu_estado);
        $this->assertSame($huellaOriginal, $factura->huella);
        $this->assertSame($huellaAnteriorOriginal, $factura->huella_anterior);
        $this->assertSame($xmlOriginal, $factura->registro_xml);
        $this->assertEquals($registradaAtOriginal, $factura->registrada_at);

        $this->assertSame(
            1,
            \App\Models\Factura::where('tenant_id', $tenant->id)->whereNotNull('huella')->count(),
            'El reintento no debe crear ningún eslabón nuevo en la cadena.',
        );
    }

    public function test_el_comando_de_reintento_automatico_reencola_las_facturas_en_error(): void
    {
        $intentos = 0;
        Http::fake([
            '*' => function () use (&$intentos) {
                $intentos++;

                return $intentos === 1
                    ? Http::response('Bad Gateway', 502)
                    : Http::response('<RespuestaRegFactuSistemaFacturacion><EstadoEnvio>Correcto</EstadoEnvio><CSV>CSV-AUTO</CSV><RespuestaLinea><EstadoRegistro>Correcto</EstadoRegistro></RespuestaLinea></RespuestaRegFactuSistemaFacturacion>', 200);
            },
        ]);

        $tenant = $this->tenantConVerifactuYCertificado();
        $factura = $this->facturaEmitida($tenant);
        $this->assertSame(VerifactuEstado::Error, $factura->refresh()->verifactu_estado);

        $this->artisan('verifactu:reintentar')->assertExitCode(0);

        $this->assertSame(VerifactuEstado::Enviada, $factura->refresh()->verifactu_estado);
    }

    public function test_el_reintento_manual_por_http_reenvia_una_factura_en_error(): void
    {
        $intentos = 0;
        Http::fake([
            '*' => function () use (&$intentos) {
                $intentos++;

                return $intentos === 1
                    ? Http::response('Bad Gateway', 502)
                    : Http::response('<RespuestaRegFactuSistemaFacturacion><EstadoEnvio>Correcto</EstadoEnvio><CSV>CSV-MANUAL</CSV><RespuestaLinea><EstadoRegistro>Correcto</EstadoRegistro></RespuestaLinea></RespuestaRegFactuSistemaFacturacion>', 200);
            },
        ]);

        $tenant = $this->tenantConVerifactuYCertificado();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $factura = $this->facturaEmitida($tenant);
        $this->assertSame(VerifactuEstado::Error, $factura->refresh()->verifactu_estado);

        $this->loginAs($user);

        $response = $this->post("/facturas/{$factura->id}/verifactu/reintentar");

        $response->assertSessionHas('success');
        $this->assertSame(VerifactuEstado::Enviada, $factura->refresh()->verifactu_estado);
    }

    public function test_el_reintento_manual_rechaza_una_factura_que_no_esta_en_error(): void
    {
        Http::fake([
            '*' => Http::response('<RespuestaRegFactuSistemaFacturacion><EstadoEnvio>Correcto</EstadoEnvio><CSV>CSV-OK</CSV><RespuestaLinea><EstadoRegistro>Correcto</EstadoRegistro></RespuestaLinea></RespuestaRegFactuSistemaFacturacion>', 200),
        ]);

        $tenant = $this->tenantConVerifactuYCertificado();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $factura = $this->facturaEmitida($tenant);
        $this->assertSame(VerifactuEstado::Enviada, $factura->refresh()->verifactu_estado);

        $this->loginAs($user);

        $response = $this->post("/facturas/{$factura->id}/verifactu/reintentar");

        $response->assertSessionHas('error');
    }

    public function test_con_el_flag_activo_y_sin_certificado_la_factura_se_emite_y_registra_y_el_envio_queda_en_error(): void
    {
        // Sin CertificadoTenant::guardar(): el tenant no tiene certificado configurado (FR-018).
        $tenant = Tenant::factory()->create(['nif' => 'A58818501']);
        VerifactuTenant::activar($tenant->id, true);

        $factura = $this->facturaEmitida($tenant);
        $factura->refresh();

        $this->assertSame('emitida', $factura->estado->value);
        $this->assertNotEmpty($factura->huella);
        $this->assertSame(VerifactuEstado::Error, $factura->verifactu_estado);
    }

    public function test_registrar_anulacion_genera_un_registro_encadenado_y_lo_remite(): void
    {
        Http::fake([
            '*' => Http::response('<RespuestaRegFactuSistemaFacturacion><EstadoEnvio>Correcto</EstadoEnvio><CSV>CSV-ALTA</CSV><RespuestaLinea><EstadoRegistro>Correcto</EstadoRegistro></RespuestaLinea></RespuestaRegFactuSistemaFacturacion>', 200),
        ]);

        $tenant = $this->tenantConVerifactuYCertificado();
        $factura = $this->facturaEmitida($tenant);
        $huellaAlta = $factura->refresh()->huella;

        Http::fake([
            '*' => Http::response('<RespuestaRegFactuSistemaFacturacion><EstadoEnvio>Correcto</EstadoEnvio><CSV>CSV-ANULA</CSV><RespuestaLinea><EstadoRegistro>Correcto</EstadoRegistro></RespuestaLinea></RespuestaRegFactuSistemaFacturacion>', 200),
        ]);

        app(RegistroVerifactu::class)->registrarAnulacion($factura, 'Error en la operación');

        $evento = $factura->eventos()->where('tipo_evento', 'verifactu_anulacion')->first();
        $this->assertNotNull($evento);
        $this->assertSame($huellaAlta, $evento->detalle['huella_anterior']);
        $this->assertNotEmpty($evento->huella);

        // La huella de alta de la factura nunca se toca al anular.
        $this->assertSame($huellaAlta, $factura->refresh()->huella);

        $this->assertDatabaseHas('factura_eventos', [
            'factura_id' => $factura->id,
            'tipo_evento' => 'verifactu_enviado',
        ]);
    }

    public function test_el_disparador_de_anulacion_pasa_la_factura_a_anulada_y_registra_la_anulacion_verifactu(): void
    {
        Http::fake([
            '*' => Http::response('<RespuestaRegFactuSistemaFacturacion><EstadoEnvio>Correcto</EstadoEnvio><CSV>CSV-OK</CSV><RespuestaLinea><EstadoRegistro>Correcto</EstadoRegistro></RespuestaLinea></RespuestaRegFactuSistemaFacturacion>', 200),
        ]);

        $tenant = $this->tenantConVerifactuYCertificado();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $factura = $this->facturaEmitida($tenant);

        $this->loginAs($user);

        $response = $this->post("/facturas/{$factura->id}/anular", ['motivo' => 'Registro erróneo, sin efectos económicos']);

        $response->assertRedirect(route('facturas.index'));

        $factura->refresh();
        $this->assertSame('anulada', $factura->estado->value);
        $this->assertDatabaseHas('factura_eventos', ['factura_id' => $factura->id, 'tipo_evento' => 'anulada']);
        $this->assertDatabaseHas('factura_eventos', ['factura_id' => $factura->id, 'tipo_evento' => 'verifactu_anulacion']);
    }

    public function test_no_se_puede_anular_una_factura_con_cobros_registrados(): void
    {
        Http::fake([
            '*' => Http::response('<RespuestaRegFactuSistemaFacturacion><EstadoEnvio>Correcto</EstadoEnvio><CSV>CSV-OK</CSV><RespuestaLinea><EstadoRegistro>Correcto</EstadoRegistro></RespuestaLinea></RespuestaRegFactuSistemaFacturacion>', 200),
        ]);

        $tenant = $this->tenantConVerifactuYCertificado();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $factura = $this->facturaEmitida($tenant);
        $factura->pagos()->create(['tenant_id' => $tenant->id, 'fecha' => now()->toDateString(), 'metodo' => 'efectivo', 'importe' => 121]);

        $this->loginAs($user);

        $response = $this->post("/facturas/{$factura->id}/anular", ['motivo' => 'intento inválido']);

        $response->assertSessionHas('error');
        $this->assertSame('emitida', $factura->refresh()->estado->value);
    }
}
