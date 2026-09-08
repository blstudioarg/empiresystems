<?php

namespace Tests\Feature;

use App\Models\Articulo;
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
 * Principio I (NON-NEGOTIABLE): nada de esta feature puede cruzar la frontera entre empresas.
 *
 * Los tokens ajenos responden **404 y no 403** a propósito: un 403 confirmaría que el token existe,
 * y eso ya es una filtración.
 */
class CompraDocumentoAislamientoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('documentos');
    }

    private function tenantConAdmin(): array
    {
        $tenant = Tenant::factory()->create();
        IaTenant::guardarApiKey('sk-de-prueba', $tenant->id);
        $user = User::factory()->admin()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);

        return [$tenant, $user];
    }

    private function subirComo(User $user): string
    {
        $this->loginAs($user);

        return $this->postJson('/compras/documentos', [
            'archivos' => [UploadedFile::fake()->createWithContent('factura.pdf', "%PDF-1.4\n/Type /Page\n")],
        ])->json('documentos.0.token');
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

    /** T047 */
    public function test_un_token_del_tenant_a_no_se_puede_interpretar_desde_el_tenant_b(): void
    {
        [, $userA] = $this->tenantConAdmin();
        [, $userB] = $this->tenantConAdmin();

        $token = $this->subirComo($userA);

        $this->fingirLectura();
        $this->loginAs($userB);

        $this->postJson("/compras/documentos/{$token}/interpretar")->assertStatus(404);
    }

    /** T047 */
    public function test_un_token_del_tenant_a_no_se_puede_confirmar_desde_el_tenant_b(): void
    {
        [, $userA] = $this->tenantConAdmin();
        [$tenantB, $userB] = $this->tenantConAdmin();

        $token = $this->subirComo($userA);

        $this->loginAs($userB);
        $proveedorB = Proveedor::factory()->create(['tenant_id' => $tenantB->id]);

        $this->postJson("/compras/documentos/{$token}/crear", [
            'proveedor_id' => $proveedorB->id,
            'crear_proveedor' => false,
            'fecha' => '2026-03-14',
            'lineas' => [['articulo_id' => null, 'concepto' => 'X', 'cantidad' => 1, 'precio_unitario' => 10, 'tipo_impositivo' => 21]],
        ])->assertStatus(404);

        $this->assertDatabaseCount('compras', 0);
    }

    /** T048 */
    public function test_un_proveedor_de_otro_tenant_es_fallo_de_validacion_no_un_acceso(): void
    {
        [$tenantA, $userA] = $this->tenantConAdmin();
        [$tenantB] = $this->tenantConAdmin();

        $proveedorAjeno = Proveedor::factory()->create(['tenant_id' => $tenantB->id]);
        $token = $this->subirComo($userA);

        $respuesta = $this->postJson("/compras/documentos/{$token}/crear", [
            'proveedor_id' => $proveedorAjeno->id,
            'crear_proveedor' => false,
            'fecha' => '2026-03-14',
            'lineas' => [['articulo_id' => null, 'concepto' => 'X', 'cantidad' => 1, 'precio_unitario' => 10, 'tipo_impositivo' => 21]],
        ]);

        $respuesta->assertStatus(422);
        $respuesta->assertJsonValidationErrors('proveedor_id');
        $this->assertDatabaseCount('compras', 0);
    }

    /** T048 */
    public function test_un_articulo_de_otro_tenant_es_fallo_de_validacion(): void
    {
        [$tenantA, $userA] = $this->tenantConAdmin();
        [$tenantB] = $this->tenantConAdmin();

        $articuloAjeno = Articulo::factory()->create(['tenant_id' => $tenantB->id]);
        $proveedorPropio = Proveedor::factory()->create(['tenant_id' => $tenantA->id]);
        $token = $this->subirComo($userA);

        $respuesta = $this->postJson("/compras/documentos/{$token}/crear", [
            'proveedor_id' => $proveedorPropio->id,
            'crear_proveedor' => false,
            'fecha' => '2026-03-14',
            'lineas' => [['articulo_id' => $articuloAjeno->id, 'concepto' => 'X', 'cantidad' => 1, 'precio_unitario' => 10, 'tipo_impositivo' => 21]],
        ]);

        $respuesta->assertStatus(422);
        $respuesta->assertJsonValidationErrors('lineas.0.articulo_id');
        $this->assertDatabaseCount('compras', 0);
    }

    /** T049 */
    public function test_no_se_puede_descargar_el_documento_de_una_compra_de_otro_tenant(): void
    {
        [$tenantA, $userA] = $this->tenantConAdmin();
        [, $userB] = $this->tenantConAdmin();

        $proveedorA = Proveedor::factory()->create(['tenant_id' => $tenantA->id]);
        $token = $this->subirComo($userA);

        $this->postJson("/compras/documentos/{$token}/crear", [
            'proveedor_id' => $proveedorA->id,
            'crear_proveedor' => false,
            'fecha' => '2026-03-14',
            'lineas' => [['articulo_id' => null, 'concepto' => 'X', 'cantidad' => 1, 'precio_unitario' => 10, 'tipo_impositivo' => 21]],
        ])->assertStatus(201);

        $compraA = Compra::withoutGlobalScopes()->firstOrFail();

        $this->loginAs($userB);
        $this->get("/compras/{$compraA->id}/documento")->assertStatus(404);
    }

    /** T049 */
    public function test_el_listado_del_tenant_b_nunca_incluye_compras_del_tenant_a(): void
    {
        [$tenantA, $userA] = $this->tenantConAdmin();
        [, $userB] = $this->tenantConAdmin();

        $proveedorA = Proveedor::factory()->create(['tenant_id' => $tenantA->id]);
        $token = $this->subirComo($userA);

        $this->postJson("/compras/documentos/{$token}/crear", [
            'proveedor_id' => $proveedorA->id,
            'crear_proveedor' => false,
            'numero_documento' => 'SOLO-DE-A',
            'fecha' => '2026-03-14',
            'lineas' => [['articulo_id' => null, 'concepto' => 'X', 'cantidad' => 1, 'precio_unitario' => 10, 'tipo_impositivo' => 21]],
        ])->assertStatus(201);

        $this->loginAs($userB);
        $respuesta = $this->getJson('/compras');

        $respuesta->assertOk();
        $respuesta->assertJsonCount(0, 'data');
        $respuesta->assertDontSee('SOLO-DE-A');
    }

    /** T049 — el descarte tampoco puede alcanzar el documento ajeno. */
    public function test_descartar_desde_otro_tenant_no_borra_el_documento_ajeno(): void
    {
        [, $userA] = $this->tenantConAdmin();
        [, $userB] = $this->tenantConAdmin();

        $token = $this->subirComo($userA);

        $this->loginAs($userB);
        $this->deleteJson("/compras/documentos/{$token}")->assertOk(); // idempotente, no revela nada

        // Sigue existiendo para su dueño.
        $this->fingirLectura();
        $this->loginAs($userA);
        $this->postJson("/compras/documentos/{$token}/interpretar")->assertOk();
    }
}
