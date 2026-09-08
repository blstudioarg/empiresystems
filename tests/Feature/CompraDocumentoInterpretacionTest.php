<?php

namespace Tests\Feature;

use App\Exceptions\DocumentoCompraException;
use App\Models\Compra;
use App\Models\CompraLinea;
use App\Models\Proveedor;
use App\Models\Tenant;
use App\Models\User;
use App\Services\InterpretadorDocumentoCompra;
use App\Support\IaTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Ningún test llama a la API real: `InterpretadorDocumentoCompra` se sustituye por un doble.
 */
class CompraDocumentoInterpretacionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function tenantConIa(): Tenant
    {
        $tenant = Tenant::factory()->create();
        IaTenant::guardarApiKey('sk-de-prueba', $tenant->id);

        return $tenant;
    }

    private function subirDocumento(): string
    {
        return $this->postJson('/compras/documentos', [
            'archivos' => [UploadedFile::fake()->createWithContent('factura.pdf', "%PDF-1.4\n/Type /Page\n")],
        ])->json('documentos.0.token');
    }

    private function fingirLectura(array $lectura): void
    {
        $doble = $this->createMock(InterpretadorDocumentoCompra::class);
        $doble->method('interpretar')->willReturn($lectura);

        $this->app->instance(InterpretadorDocumentoCompra::class, $doble);
    }

    private function fingirFallo(DocumentoCompraException $e): void
    {
        $doble = $this->createMock(InterpretadorDocumentoCompra::class);
        $doble->method('interpretar')->willThrowException($e);

        $this->app->instance(InterpretadorDocumentoCompra::class, $doble);
    }

    private function lecturaValida(): array
    {
        return [
            'es_documento_compra' => true,
            'emisor' => ['nombre' => 'Suministros SL', 'nif' => 'B12345678'],
            'numero_documento' => 'F-2026/1183',
            'fecha' => '2026-03-14',
            'moneda' => 'EUR',
            'lineas' => [
                ['concepto' => 'Tornillo M6', 'referencia' => 'T-M6', 'unidad' => 'ud', 'cantidad' => 500, 'precio_unitario' => 0.12, 'tipo_impositivo' => 21],
            ],
            'total_documento' => 72.60,
        ];
    }

    public function test_interpretar_devuelve_la_propuesta_con_los_importes_calculados(): void
    {
        $tenant = $this->tenantConIa();
        $this->loginAs(User::factory()->admin()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]));

        $token = $this->subirDocumento();
        $this->fingirLectura($this->lecturaValida());

        $respuesta = $this->postJson("/compras/documentos/{$token}/interpretar", ['archivo_nombre' => 'factura.pdf']);

        $respuesta->assertOk();
        $respuesta->assertJsonPath('numero_documento', 'F-2026/1183');
        // JSON no distingue 60 de 60.0: comparo el valor, no su tipo.
        $this->assertEqualsWithDelta(60.0, $respuesta->json('totales.base_total'), 0.001);
        $this->assertEqualsWithDelta(72.6, $respuesta->json('totales.total'), 0.001);
        $respuesta->assertJsonPath('archivo_nombre', 'factura.pdf');
    }

    /**
     * FR-011: la garantía central del flujo — interpretar no persiste absolutamente nada.
     */
    public function test_interpretar_no_persiste_nada(): void
    {
        $tenant = $this->tenantConIa();
        $this->loginAs(User::factory()->admin()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]));

        $comprasAntes = Compra::count();
        $lineasAntes = CompraLinea::count();
        $proveedoresAntes = Proveedor::count();

        $token = $this->subirDocumento();
        $this->fingirLectura($this->lecturaValida());
        $this->postJson("/compras/documentos/{$token}/interpretar")->assertOk();

        $this->assertSame($comprasAntes, Compra::count());
        $this->assertSame($lineasAntes, CompraLinea::count());
        $this->assertSame($proveedoresAntes, Proveedor::count(), 'El proveedor del documento no se crea al interpretar.');
    }

    public function test_un_documento_que_no_es_de_compra_responde_422_nombrando_el_archivo(): void
    {
        $tenant = $this->tenantConIa();
        $this->loginAs(User::factory()->admin()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]));

        $token = $this->subirDocumento();
        $this->fingirFallo(new DocumentoCompraException(
            DocumentoCompraException::NO_ES_DOCUMENTO_COMPRA,
            'No se pudo interpretar «recibo-parking.jpg» como un documento de compra.',
            'Parece un recibo de aparcamiento.',
        ));

        $respuesta = $this->postJson("/compras/documentos/{$token}/interpretar", ['archivo_nombre' => 'recibo-parking.jpg']);

        $respuesta->assertStatus(422);
        $respuesta->assertJsonPath('codigo', 'no_es_documento_compra');
        $respuesta->assertJsonPath('archivo_nombre', 'recibo-parking.jpg');
        $this->assertStringContainsString('aparcamiento', $respuesta->json('motivo'));
    }

    public static function erroresDelProveedor(): array
    {
        return [
            'clave inválida' => [DocumentoCompraException::CLAVE_INVALIDA, 422],
            'cuota agotada' => [DocumentoCompraException::LIMITE_EXCEDIDO, 429],
            'servicio caído' => [DocumentoCompraException::SERVICIO_NO_DISPONIBLE, 502],
            'error interno' => [DocumentoCompraException::INTERNO, 500],
        ];
    }

    /**
     * @dataProvider erroresDelProveedor
     */
    public function test_cada_error_del_proveedor_se_mapea_a_su_codigo_y_estado(string $codigo, int $estado): void
    {
        $tenant = $this->tenantConIa();
        $this->loginAs(User::factory()->admin()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]));

        $token = $this->subirDocumento();
        $this->fingirFallo(new DocumentoCompraException($codigo, 'Falló.', 'detalle técnico'));

        $respuesta = $this->postJson("/compras/documentos/{$token}/interpretar");

        $respuesta->assertStatus($estado);
        $respuesta->assertJsonPath('codigo', $codigo);
    }

    public function test_el_detalle_tecnico_no_se_expone_a_quien_no_administra_la_configuracion(): void
    {
        $tenant = $this->tenantConIa();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $this->loginAs($user);

        // Rol base: tiene ver-compras pero no ver-configuracion.
        if (! $user->can('ver-compras')) {
            $this->markTestSkipped('El rol base de este proyecto no incluye ver-compras.');
        }

        $token = $this->subirDocumento();
        $this->fingirFallo(new DocumentoCompraException(DocumentoCompraException::INTERNO, 'Falló.', 'clave sk-xxx rechazada'));

        $respuesta = $this->postJson("/compras/documentos/{$token}/interpretar");

        $this->assertNull($respuesta->json('detalle'));
        $respuesta->assertDontSee('sk-xxx');
    }

    public function test_un_token_inexistente_responde_404(): void
    {
        $tenant = $this->tenantConIa();
        $this->loginAs(User::factory()->admin()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]));

        $this->fingirLectura($this->lecturaValida());

        $this->postJson('/compras/documentos/'.Str::uuid()->toString().'/interpretar')
            ->assertStatus(404);
    }
}
