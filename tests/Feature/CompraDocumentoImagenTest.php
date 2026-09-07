<?php

namespace Tests\Feature;

use App\Models\Compra;
use App\Models\Proveedor;
use App\Models\Tenant;
use App\Models\User;
use App\Services\InterpretadorDocumentoCompra;
use App\Support\IaTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * El flujo admite fotos además de PDFs: una factura sacada con el móvil es el caso más común en la
 * calle. Cada formato viaja al modelo de una forma distinta (research D1) — el PDF como parte
 * `file`, la imagen como `image_url` con data URI — y esta suite fija ese contrato.
 */
class CompraDocumentoImagenTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('documentos');
    }

    private function escenario(): array
    {
        $tenant = Tenant::factory()->create();
        IaTenant::guardarApiKey('sk-de-prueba', $tenant->id);
        $this->loginAs(User::factory()->admin()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]));

        return [$tenant, Proveedor::factory()->create(['tenant_id' => $tenant->id])];
    }

    private function fingirLectura(): void
    {
        $doble = $this->createMock(InterpretadorDocumentoCompra::class);
        $doble->method('interpretar')->willReturn([
            'es_documento_compra' => true,
            'emisor' => ['nombre' => 'Suministros SL', 'nif' => 'B12345678'],
            'numero_documento' => 'F-1', 'fecha' => '2026-03-14', 'moneda' => 'EUR',
            'lineas' => [['concepto' => 'X', 'referencia' => null, 'unidad' => 'ud', 'cantidad' => 1, 'precio_unitario' => 10, 'tipo_impositivo' => 21]],
            'total_documento' => 12.10,
        ]);

        $this->app->instance(InterpretadorDocumentoCompra::class, $doble);
    }

    public static function formatosDeImagen(): array
    {
        return [
            'jpg' => ['factura.jpg', 'image/jpeg'],
            'jpeg' => ['factura.jpeg', 'image/jpeg'],
            'png' => ['factura.png', 'image/png'],
            'webp' => ['factura.webp', 'image/webp'],
        ];
    }

    /**
     * @dataProvider formatosDeImagen
     */
    public function test_se_admiten_los_cuatro_formatos_de_imagen(string $nombre, string $mime): void
    {
        $this->escenario();

        $respuesta = $this->postJson('/compras/documentos', [
            'archivos' => [UploadedFile::fake()->image($nombre)],
        ]);

        $respuesta->assertOk();
        $respuesta->assertJsonPath('documentos.0.archivo_nombre', $nombre);
        $respuesta->assertJsonPath('documentos.0.formato', 'imagen');
    }

    public function test_una_imagen_no_pasa_por_el_contador_de_paginas(): void
    {
        $this->escenario();
        config(['compras.documentos.max_paginas_pdf' => 1]);

        // El límite de páginas es cosa de PDFs: una foto nunca debe rebotar por eso.
        $this->postJson('/compras/documentos', ['archivos' => [UploadedFile::fake()->image('foto.jpg')]])
            ->assertOk()
            ->assertJsonCount(0, 'rechazados');
    }

    public function test_una_compra_creada_desde_una_foto_queda_marcada_como_imagen(): void
    {
        [, $proveedor] = $this->escenario();

        $token = $this->postJson('/compras/documentos', [
            'archivos' => [UploadedFile::fake()->image('factura.jpg')],
        ])->json('documentos.0.token');

        $this->fingirLectura();
        $this->postJson("/compras/documentos/{$token}/interpretar")->assertOk();

        $this->postJson("/compras/documentos/{$token}/crear", [
            'proveedor_id' => $proveedor->id,
            'crear_proveedor' => false,
            'numero_documento' => 'F-1',
            'fecha' => '2026-03-14',
            'lineas' => [['articulo_id' => null, 'concepto' => 'X', 'cantidad' => 1, 'precio_unitario' => 10, 'tipo_impositivo' => 21]],
        ])->assertStatus(201);

        $compra = Compra::firstOrFail();

        $this->assertSame('imagen', $compra->formato_recepcion, 'Una foto no es un pdf.');
        $this->assertStringEndsWith('.jpg', $compra->archivo_recibido_path);
    }

    public function test_la_descarga_de_una_foto_devuelve_el_mime_de_imagen(): void
    {
        [, $proveedor] = $this->escenario();

        $token = $this->postJson('/compras/documentos', [
            'archivos' => [UploadedFile::fake()->image('factura.png')],
        ])->json('documentos.0.token');

        $this->fingirLectura();
        $this->postJson("/compras/documentos/{$token}/crear", [
            'proveedor_id' => $proveedor->id,
            'crear_proveedor' => false,
            'fecha' => '2026-03-14',
            'lineas' => [['articulo_id' => null, 'concepto' => 'X', 'cantidad' => 1, 'precio_unitario' => 10, 'tipo_impositivo' => 21]],
        ])->assertStatus(201);

        $compra = Compra::firstOrFail();

        $this->get("/compras/{$compra->id}/documento")
            ->assertOk()
            ->assertHeader('content-type', 'image/png');
    }

    /**
     * El contrato con el proveedor de IA: una imagen va como `image_url` con data URI y un PDF
     * como parte `file`. Si esto cambia, el modelo deja de ver el documento.
     */
    public function test_el_interpretador_manda_la_imagen_como_image_url_y_el_pdf_como_file(): void
    {
        $interpretador = new InterpretadorDocumentoCompra;
        $metodo = new \ReflectionMethod($interpretador, 'parteDelDocumento');

        $imagen = $metodo->invoke($interpretador, 'bytes-de-la-foto', 'factura.png', 'png');
        $this->assertSame('image_url', $imagen['type']);
        $this->assertStringStartsWith('data:image/png;base64,', $imagen['image_url']['url']);

        $jpg = $metodo->invoke($interpretador, 'bytes', 'factura.jpg', 'jpg');
        $this->assertStringStartsWith('data:image/jpeg;base64,', $jpg['image_url']['url']);

        $webp = $metodo->invoke($interpretador, 'bytes', 'factura.webp', 'webp');
        $this->assertStringStartsWith('data:image/webp;base64,', $webp['image_url']['url']);

        $pdf = $metodo->invoke($interpretador, 'bytes-del-pdf', 'factura.pdf', 'pdf');
        $this->assertSame('file', $pdf['type']);
        $this->assertSame('factura.pdf', $pdf['file']['filename']);
        $this->assertStringStartsWith('data:application/pdf;base64,', $pdf['file']['file_data']);
    }
}
