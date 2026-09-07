<?php

namespace Tests\Unit;

use App\Models\Tenant;
use App\Services\AlmacenDocumentosCompra;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class AlmacenDocumentosCompraTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Sin esto los ficheros de un test sobreviven al siguiente (RefreshDatabase solo revierte
        // la base) y la purga cuenta huérfanos ajenos — además de ensuciar el storage real.
        Storage::fake('local');
    }

    private function almacen(): AlmacenDocumentosCompra
    {
        return new AlmacenDocumentosCompra;
    }

    private function pdf(string $nombre = 'factura.pdf'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($nombre, "%PDF-1.4\n/Type /Page\n");
    }

    public function test_guardar_devuelve_un_token_que_resuelve_a_un_fichero_existente(): void
    {
        tenancy()->initialize(Tenant::factory()->create());

        $token = $this->almacen()->guardar($this->pdf());

        $this->assertNotEmpty($token);
        $this->assertNotNull($this->almacen()->rutaAbsoluta($token));
        $this->assertFileExists($this->almacen()->rutaAbsoluta($token));
        $this->assertSame('pdf', $this->almacen()->extension($token));
    }

    public function test_un_token_inexistente_devuelve_null(): void
    {
        tenancy()->initialize(Tenant::factory()->create());

        $this->assertNull($this->almacen()->rutaAbsoluta((string) Str::uuid()));
        $this->assertNull($this->almacen()->rutaAbsoluta('no-es-un-uuid'));
    }

    public function test_un_fichero_de_mas_de_24_horas_se_considera_caducado_aunque_siga_en_disco(): void
    {
        tenancy()->initialize(Tenant::factory()->create());

        $token = $this->almacen()->guardar($this->pdf());
        $ruta = $this->almacen()->rutaAbsoluta($token);

        // La purga todavía no pasó: el fichero sigue ahí, pero ya no debe resolverse.
        touch($ruta, now()->subHours(25)->timestamp);
        clearstatcache();

        $this->assertFileExists($ruta);
        $this->assertNull($this->almacen()->rutaAbsoluta($token));
    }

    public function test_borrar_es_idempotente(): void
    {
        tenancy()->initialize(Tenant::factory()->create());

        $token = $this->almacen()->guardar($this->pdf());

        $this->almacen()->borrar($token);
        $this->assertNull($this->almacen()->rutaAbsoluta($token));

        // Segundo borrado sobre un token ya inexistente: no debe lanzar.
        $this->almacen()->borrar($token);
        $this->assertNull($this->almacen()->rutaAbsoluta($token));
    }

    public function test_purgar_huerfanos_borra_solo_los_caducados_y_devuelve_el_conteo(): void
    {
        tenancy()->initialize(Tenant::factory()->create());

        $vigente = $this->almacen()->guardar($this->pdf('vigente.pdf'));
        $caducado = $this->almacen()->guardar($this->pdf('caducado.pdf'));

        $rutaCaducada = $this->almacen()->rutaAbsoluta($caducado);
        touch($rutaCaducada, now()->subHours(25)->timestamp);
        clearstatcache();

        $this->assertSame(1, $this->almacen()->purgarHuerfanos());
        $this->assertFileDoesNotExist($rutaCaducada);
        $this->assertNotNull($this->almacen()->rutaAbsoluta($vigente));
    }

    /**
     * Principio I: sin esta garantía, un token filtrado daría acceso al documento de otra empresa.
     */
    public function test_un_token_del_tenant_a_no_se_resuelve_estando_activo_el_tenant_b(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        tenancy()->initialize($tenantA);
        $token = $this->almacen()->guardar($this->pdf());
        $this->assertNotNull($this->almacen()->rutaAbsoluta($token));

        tenancy()->initialize($tenantB);
        $this->assertNull($this->almacen()->rutaAbsoluta($token), 'Un token de otro tenant debe ser indistinguible de uno inexistente.');
        $this->assertNull($this->almacen()->extension($token));

        // Y borrarlo desde B no puede tocar el fichero de A.
        $this->almacen()->borrar($token);

        tenancy()->initialize($tenantA);
        $this->assertNotNull($this->almacen()->rutaAbsoluta($token), 'El borrado desde otro tenant no debe alcanzar el fichero.');
    }

    public function test_purgar_huerfanos_barre_todos_los_tenants(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        tenancy()->initialize($tenantA);
        $tokenA = $this->almacen()->guardar($this->pdf());
        touch($this->almacen()->rutaAbsoluta($tokenA), now()->subHours(25)->timestamp);

        tenancy()->initialize($tenantB);
        $tokenB = $this->almacen()->guardar($this->pdf());
        touch($this->almacen()->rutaAbsoluta($tokenB), now()->subHours(25)->timestamp);
        clearstatcache();

        // El comando corre fuera de contexto de tenant.
        tenancy()->end();

        $this->assertSame(2, $this->almacen()->purgarHuerfanos());
    }
}
