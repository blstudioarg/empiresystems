<?php

namespace Tests\Feature;

use App\Models\Articulo;
use App\Models\Compra;
use App\Models\Proveedor;
use App\Models\Tenant;
use App\Models\User;
use App\Support\IaTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Renderiza de verdad las vistas que toca la feature 044.
 *
 * Existe porque faltaba: toda la suite probaba endpoints JSON, así que un error de sintaxis Blade
 * en `compras/index.blade.php` llegó a producir un 500 en la pantalla sin que ningún test se
 * enterara. Un `assertOk()` sobre el HTML es barato y ataja justo esa clase de fallo.
 */
class CompraDocumentoVistaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('documentos');
    }

    private function tenantConDatos(bool $conIa = true): array
    {
        $tenant = Tenant::factory()->create();

        if ($conIa) {
            IaTenant::guardarApiKey('sk-de-prueba', $tenant->id);
        }

        $user = User::factory()->admin()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $this->loginAs($user);

        return [$tenant, $user];
    }

    public function test_el_listado_de_compras_renderiza_con_el_modal_de_importacion(): void
    {
        [$tenant] = $this->tenantConDatos();

        Proveedor::factory()->count(2)->create(['tenant_id' => $tenant->id]);
        Articulo::factory()->producto()->create(['tenant_id' => $tenant->id, 'gestion_stock' => true]);

        $respuesta = $this->get('/compras');

        $respuesta->assertOk();
        $respuesta->assertSee('Importar documento', false);
        $respuesta->assertSee('importarDocumentoModal', false);
        $respuesta->assertSee('initImportacionDocumentosCompra', false);
    }

    public function test_el_listado_renderiza_aunque_no_haya_proveedores_ni_articulos(): void
    {
        $this->tenantConDatos();

        $this->get('/compras')->assertOk();
    }

    /**
     * FR-007: sin clave de IA el botón se ve pero deshabilitado, y no se carga el JS del modal.
     */
    public function test_sin_clave_de_ia_el_boton_aparece_deshabilitado_y_sin_su_javascript(): void
    {
        $this->tenantConDatos(conIa: false);

        $respuesta = $this->get('/compras');

        $respuesta->assertOk();
        $respuesta->assertSee('Importar documento', false);
        $respuesta->assertSee('disabled', false);
        $respuesta->assertDontSee('initImportacionDocumentosCompra', false);
    }

    public function test_el_detalle_de_una_compra_desde_documento_renderiza_con_su_enlace_de_descarga(): void
    {
        [$tenant] = $this->tenantConDatos();

        $proveedor = Proveedor::factory()->create(['tenant_id' => $tenant->id]);
        $compra = Compra::factory()->create([
            'tenant_id' => $tenant->id,
            'proveedor_id' => $proveedor->id,
            'origen' => 'documento',
            'formato_recepcion' => 'pdf',
            'archivo_recibido_path' => 'tenants/'.$tenant->id.'/compras-documentos/x.pdf',
        ]);

        $respuesta = $this->get("/compras/{$compra->id}");

        $respuesta->assertOk();
        $respuesta->assertSee('Documento (IA)', false);
        $respuesta->assertSee("/compras/{$compra->id}/documento", false);
    }

    public function test_el_detalle_de_una_compra_manual_no_muestra_la_descarga_de_documento(): void
    {
        [$tenant] = $this->tenantConDatos();

        $proveedor = Proveedor::factory()->create(['tenant_id' => $tenant->id]);
        $compra = Compra::factory()->create(['tenant_id' => $tenant->id, 'proveedor_id' => $proveedor->id]);

        $respuesta = $this->get("/compras/{$compra->id}");

        $respuesta->assertOk();
        $respuesta->assertDontSee("/compras/{$compra->id}/documento", false);
    }

    public function test_la_ayuda_de_compras_menciona_el_flujo_nuevo(): void
    {
        $this->tenantConDatos();

        $this->get('/compras')
            ->assertOk()
            ->assertSee('Importar documento', false);
    }
}
