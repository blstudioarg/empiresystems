<?php

namespace Tests\Feature;

use App\Models\Compra;
use App\Models\Proveedor;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FacturaeRecepcionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('documentos');
    }

    private function subirXml(string $nombre): \Illuminate\Testing\TestResponse
    {
        $archivo = UploadedFile::fake()->createWithContent(
            $nombre,
            file_get_contents(base_path("tests/Fixtures/facturae/{$nombre}"))
        );

        return $this->post('/compras/importar-facturae', ['archivo' => $archivo]);
    }

    public function test_xml_valido_crea_compra_con_origen_facturae_y_datos_volcados(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $this->loginAs($user);

        $response = $this->subirXml('proveedor-valido.xml');

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('compras', [
            'tenant_id' => $tenant->id,
            'origen' => 'facturae',
            'formato_recepcion' => 'facturae',
            'estado_b2b' => 'recibida',
        ]);

        $compra = Compra::first();
        $this->assertNotNull($compra->archivo_recibido_path);
        $this->assertCount(2, $compra->lineas);
        $this->assertSame('450.00', number_format((float) $compra->total, 2, '.', ''));

        $this->assertDatabaseHas('proveedores', [
            'tenant_id' => $tenant->id,
            'nif' => 'B12345674',
        ]);
    }

    public function test_archivo_invalido_se_rechaza_sin_crear_compra(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $this->loginAs($user);

        $response = $this->subirXml('proveedor-invalido.xml');

        $response->assertSessionHas('error');
        $this->assertDatabaseCount('compras', 0);
    }

    public function test_xml_con_firma_no_verificable_crea_compra_con_aviso(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $this->loginAs($user);

        $response = $this->subirXml('proveedor-firma-invalida.xml');

        $response->assertSessionHas('warning');
        $this->assertDatabaseCount('compras', 1);
    }

    public function test_xml_con_firma_verificable_no_muestra_aviso(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $this->loginAs($user);

        $response = $this->subirXml('proveedor-firmado.xml');

        $response->assertSessionHas('success');
        $response->assertSessionMissing('warning');
    }

    public function test_reimportar_mismo_emisor_numero_y_fecha_avisa_duplicado_y_no_crea_otra(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $this->loginAs($user);

        $this->subirXml('proveedor-valido.xml');
        $this->assertDatabaseCount('compras', 1);

        $response = $this->subirXml('proveedor-valido.xml');

        $response->assertSessionHas('warning');
        $this->assertDatabaseCount('compras', 1);
    }

    public function test_proveedor_inexistente_se_crea_automaticamente_desde_el_xml(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $this->loginAs($user);

        $this->assertDatabaseCount('proveedores', 0);

        $this->subirXml('proveedor-valido.xml');

        $this->assertDatabaseCount('proveedores', 1);
        $this->assertDatabaseHas('proveedores', ['tenant_id' => $tenant->id, 'nif' => 'B12345674']);
    }

    public function test_proveedor_ya_existente_se_reutiliza_por_nif(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $this->loginAs($user);

        $proveedor = Proveedor::factory()->create(['tenant_id' => $tenant->id, 'nif' => 'B12345674']);

        $this->subirXml('proveedor-valido.xml');

        $this->assertDatabaseCount('proveedores', 1);
        $compra = Compra::first();
        $this->assertSame($proveedor->id, $compra->proveedor_id);
    }

    public function test_aislamiento_a_no_importa_hacia_ni_lee_xml_de_b(): void
    {
        $tenantB = Tenant::factory()->create();
        $userB = User::factory()->create(['tenant_id' => $tenantB->id, 'password' => bcrypt('secret123')]);
        $this->loginAs($userB);
        $this->subirXml('proveedor-valido.xml');
        $compraB = Compra::first();

        $tenantA = Tenant::factory()->create();
        $userA = User::factory()->create(['tenant_id' => $tenantA->id, 'password' => bcrypt('secret123')]);
        $this->loginAs($userA);

        $response = $this->get("/compras/{$compraB->id}/facturae");
        $response->assertNotFound();

        $this->assertSame(0, Compra::count());
    }
}
