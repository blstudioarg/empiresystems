<?php

namespace Tests\Feature\Pos;

use App\Models\Factura;
use App\Support\CertificadoTenant;
use App\Support\VerifactuTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\MontaSalaPos;
use Tests\TestCase;

/**
 * Principio IV (NON-NEGOTIABLE): varios cobros parciales seguidos producen una cadena Verifactu
 * **íntegra** — cada registro apunta a la huella del anterior, sin roturas ni saltos.
 *
 * Es el único caso de esta feature que genera varias facturas desde un mismo origen (una cuenta),
 * y por eso el único que puede exponer una rotura de cadena que un ticket normal no vería nunca.
 * `CobradorCuenta` no calcula huella: delega en `RegistroTicket` → `EmisorFacturas` →
 * `RegistroVerifactu`, así que la cadena se hereda del mismo camino que cualquier ticket.
 */
class CobroParcialEncadenamientoVerifactuTest extends TestCase
{
    use MontaSalaPos, RefreshDatabase;

    private const CERT_PASSWORD = 'test1234';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('documentos');
        Http::fake([
            '*' => Http::response('<RespuestaRegFactuSistemaFacturacion><EstadoEnvio>Correcto</EstadoEnvio><CSV>CSV-OK</CSV><RespuestaLinea><EstadoRegistro>Correcto</EstadoRegistro></RespuestaLinea></RespuestaRegFactuSistemaFacturacion>', 200),
        ]);
    }

    private function activarVerifactu(): void
    {
        // NIF y demás columnas del propio `tenants` deben fijarse ANTES del primer login: el
        // paquete de tenancy cachea la instancia del tenant al inicializar el contexto, así que
        // un cambio hecho a mitad de test queda invisible para `tenant()->nif` en las peticiones
        // siguientes. Verifactu y el certificado sí se pueden activar después: se guardan en
        // `configuraciones` por `tenant_id`, sin pasar por ese singleton (patrón `ConfigPos`).
        VerifactuTenant::activar($this->tenantPos->id, true);

        $archivo = UploadedFile::fake()->createWithContent(
            'certificado.p12',
            file_get_contents(base_path('tests/Fixtures/facturae/certificado.p12')),
        );
        CertificadoTenant::guardar($archivo, self::CERT_PASSWORD, $this->tenantPos->id);
    }

    public function test_los_cobros_parciales_encadenan_su_huella_con_la_del_anterior(): void
    {
        $this->montarSala(['cobro_dividido_activo' => true], atributosTenant: ['nif' => 'A58818501']);
        $this->activarVerifactu();
        $articulo = $this->articuloPos(10.00, 10);

        $cuenta = $this->abrirCuentaCon([['articulo' => $articulo, 'cantidad' => 3]]);
        $lineaId = $cuenta['lineas'][0]['id'];
        $version = $cuenta['version'];

        foreach ([1, 1, 1] as $cantidad) {
            $this->postJson("/pos/cuentas/{$cuenta['id']}/cobrar", [
                'version' => $version,
                'lineas' => [['cuenta_linea_id' => $lineaId, 'cantidad' => $cantidad]],
            ])->assertCreated();

            $version = $this->getJson("/pos/cuentas/{$cuenta['id']}")->json('version');
        }

        $facturas = Factura::query()->orderBy('numero')->get();
        $this->assertCount(3, $facturas);

        // Cada huella es no vacía y distinta de las demás (append-only, sin colisiones).
        $huellas = $facturas->pluck('huella')->all();
        $this->assertNotContains('', $huellas);
        $this->assertNotContains(null, $huellas);
        $this->assertSame($huellas, array_unique($huellas));

        // La primera factura de la cadena no tiene anterior (huella_anterior vacía); las
        // siguientes SIEMPRE apuntan exactamente a la huella de la inmediatamente anterior — sin
        // roturas ni saltos.
        $this->assertSame('', $facturas[0]->huella_anterior ?? '');
        $this->assertSame($facturas[0]->huella, $facturas[1]->huella_anterior);
        $this->assertSame($facturas[1]->huella, $facturas[2]->huella_anterior);
    }

    public function test_intercalar_un_ticket_normal_entre_cobros_parciales_no_rompe_la_cadena(): void
    {
        $this->montarSala(['cobro_dividido_activo' => true], mesas: 2, atributosTenant: ['nif' => 'A58818501']);
        $this->activarVerifactu();
        $articulo = $this->articuloPos(10.00, 10);

        $cuenta = $this->abrirCuentaCon([['articulo' => $articulo, 'cantidad' => 2]]);
        $lineaId = $cuenta['lineas'][0]['id'];

        $this->postJson("/pos/cuentas/{$cuenta['id']}/cobrar", [
            'version' => $cuenta['version'],
            'lineas' => [['cuenta_linea_id' => $lineaId, 'cantidad' => 1]],
        ])->assertCreated();

        // Un ticket de venta directa emitido en medio, sin cuenta de por medio.
        $this->postJson('/pos', [
            'lineas' => [['concepto' => 'Café', 'cantidad' => 1, 'precio_unitario' => 1.5, 'tipo_impositivo' => 10]],
        ])->assertCreated();

        $version = $this->getJson("/pos/cuentas/{$cuenta['id']}")->json('version');
        $this->postJson("/pos/cuentas/{$cuenta['id']}/cobrar", [
            'version' => $version,
            'lineas' => [['cuenta_linea_id' => $lineaId, 'cantidad' => 1]],
        ])->assertCreated();

        $facturas = Factura::query()->orderBy('numero')->get();
        $this->assertCount(3, $facturas);

        for ($i = 1; $i < 3; $i++) {
            $this->assertSame($facturas[$i - 1]->huella, $facturas[$i]->huella_anterior);
        }
    }
}
