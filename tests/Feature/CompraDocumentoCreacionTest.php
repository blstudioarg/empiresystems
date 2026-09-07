<?php

namespace Tests\Feature;

use App\Enums\EstadoCompra;
use App\Enums\OrigenCompra;
use App\Models\Articulo;
use App\Models\Compra;
use App\Models\LogActividad;
use App\Models\Proveedor;
use App\Models\Tenant;
use App\Models\User;
use App\Support\IaTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CompraDocumentoCreacionTest extends TestCase
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

        $user = User::factory()->admin()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $this->loginAs($user);

        $proveedor = Proveedor::factory()->create(['tenant_id' => $tenant->id]);

        $token = $this->postJson('/compras/documentos', [
            'archivos' => [UploadedFile::fake()->createWithContent('factura.pdf', "%PDF-1.4\n/Type /Page\n")],
        ])->json('documentos.0.token');

        return [$tenant, $user, $proveedor, $token];
    }

    private function carga(Proveedor $proveedor, array $sobrescribir = []): array
    {
        return array_merge([
            'proveedor_id' => $proveedor->id,
            'crear_proveedor' => false,
            'numero_documento' => 'F-2026/1183',
            'fecha' => '2026-03-14',
            'lineas' => [
                ['articulo_id' => null, 'concepto' => 'Tornillo M6', 'unidad' => 'ud', 'cantidad' => 500, 'precio_unitario' => 0.12, 'tipo_impositivo' => 21],
            ],
        ], $sobrescribir);
    }

    public function test_crear_genera_una_compra_en_borrador_con_origen_documento(): void
    {
        [$tenant, , $proveedor, $token] = $this->escenario();

        $respuesta = $this->postJson("/compras/documentos/{$token}/crear", $this->carga($proveedor));

        $respuesta->assertStatus(201);

        $compra = Compra::firstOrFail();
        $this->assertSame(EstadoCompra::Borrador, $compra->estado);
        $this->assertSame(OrigenCompra::Documento, $compra->origen);
        $this->assertSame('pdf', $compra->formato_recepcion);
        $this->assertNotNull($compra->archivo_recibido_path);
        $this->assertNull($compra->estado_b2b, 'El ciclo B2B es propio de Facturae.');
        Storage::disk('documentos')->assertExists($compra->archivo_recibido_path);
    }

    /**
     * Principio III: los importes que mande el cliente se descartan; manda el servidor.
     */
    public function test_los_importes_enviados_por_el_cliente_se_ignoran(): void
    {
        [, , $proveedor, $token] = $this->escenario();

        $this->postJson("/compras/documentos/{$token}/crear", $this->carga($proveedor, [
            'base_total' => 99999,
            'total' => 99999,
            'lineas' => [
                [
                    'articulo_id' => null, 'concepto' => 'Tornillo M6', 'unidad' => 'ud',
                    'cantidad' => 500, 'precio_unitario' => 0.12, 'tipo_impositivo' => 21,
                    'base' => 99999, 'cuota_impuesto' => 99999,
                ],
            ],
        ]))->assertStatus(201);

        $compra = Compra::firstOrFail();

        // 500 × 0.12 = 60.00 ; 21 % → 12.60 ; total 72.60
        $this->assertSame('60.00', (string) $compra->base_total);
        $this->assertSame('12.60', (string) $compra->cuota_impuesto_total);
        $this->assertSame('72.60', (string) $compra->total);
        $this->assertSame('60.00', (string) $compra->lineas->first()->base);
    }

    /**
     * FR: lo que se guarda es lo que editó el usuario, no lo que leyó la IA.
     */
    public function test_se_persisten_los_valores_editados_por_el_usuario(): void
    {
        [, , $proveedor, $token] = $this->escenario();

        $this->postJson("/compras/documentos/{$token}/crear", $this->carga($proveedor, [
            'numero_documento' => 'CORREGIDO-1',
            'fecha' => '2026-04-02',
            'lineas' => [
                ['articulo_id' => null, 'concepto' => 'Concepto corregido', 'unidad' => 'caja', 'cantidad' => 3, 'precio_unitario' => 10, 'tipo_impositivo' => 10],
                ['articulo_id' => null, 'concepto' => 'Línea añadida a mano', 'unidad' => null, 'cantidad' => 1, 'precio_unitario' => 5, 'tipo_impositivo' => 10],
            ],
        ]))->assertStatus(201);

        $compra = Compra::with('lineas')->firstOrFail();

        $this->assertSame('CORREGIDO-1', $compra->numero_documento);
        $this->assertSame('2026-04-02', $compra->fecha->toDateString());
        $this->assertCount(2, $compra->lineas);
        $this->assertSame('Concepto corregido', $compra->lineas[0]->concepto);
        $this->assertSame('Línea añadida a mano', $compra->lineas[1]->concepto);
        $this->assertSame('35.00', (string) $compra->base_total);
    }

    public function test_crear_borra_el_documento_temporal(): void
    {
        [, , $proveedor, $token] = $this->escenario();

        $this->postJson("/compras/documentos/{$token}/crear", $this->carga($proveedor))->assertStatus(201);

        // El token ya no resuelve: el temporal se consumió.
        $this->postJson("/compras/documentos/{$token}/crear", $this->carga($proveedor))->assertStatus(404);
    }

    /**
     * FR-039: queda constancia en el log de actividad.
     */
    public function test_crear_deja_entrada_en_el_log_de_actividad(): void
    {
        [, $user, $proveedor, $token] = $this->escenario();

        $this->postJson("/compras/documentos/{$token}/crear", $this->carga($proveedor))->assertStatus(201);

        $compra = Compra::firstOrFail();
        $log = LogActividad::where('entidad_tipo', 'compra')->where('entidad_id', $compra->id)->first();

        $this->assertNotNull($log, 'La creación desde documento debe registrarse.');
        $this->assertSame($user->id, $log->usuario_id);
    }

    /**
     * FR-030: el documento original se puede volver a descargar desde la compra.
     */
    public function test_se_puede_descargar_el_documento_original(): void
    {
        [, , $proveedor, $token] = $this->escenario();

        $this->postJson("/compras/documentos/{$token}/crear", $this->carga($proveedor))->assertStatus(201);

        $compra = Compra::firstOrFail();

        $this->get("/compras/{$compra->id}/documento")
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_la_descarga_responde_404_si_la_compra_es_de_otro_origen(): void
    {
        $tenant = Tenant::factory()->create();
        $this->loginAs(User::factory()->admin()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]));

        $proveedor = Proveedor::factory()->create(['tenant_id' => $tenant->id]);
        $compra = Compra::factory()->create(['tenant_id' => $tenant->id, 'proveedor_id' => $proveedor->id]);

        $this->get("/compras/{$compra->id}/documento")->assertStatus(404);
    }

    /**
     * FR-019: el proveedor solo se da de alta si el usuario lo pide explícitamente.
     */
    public function test_crear_proveedor_da_de_alta_proveedor_y_compra(): void
    {
        [, , , $token] = $this->escenario();
        $proveedoresAntes = Proveedor::count();

        $this->postJson("/compras/documentos/{$token}/crear", [
            'crear_proveedor' => true,
            'proveedor_nuevo' => ['nombre' => 'Ferretería Del Sur', 'nif' => 'B77777777', 'ciudad' => 'Cádiz'],
            'numero_documento' => 'F-9',
            'fecha' => '2026-03-14',
            'lineas' => [
                ['articulo_id' => null, 'concepto' => 'X', 'cantidad' => 1, 'precio_unitario' => 10, 'tipo_impositivo' => 21],
            ],
        ])->assertStatus(201);

        $this->assertSame($proveedoresAntes + 1, Proveedor::count());

        $nuevo = Proveedor::where('nombre', 'Ferretería Del Sur')->firstOrFail();
        $this->assertSame('B77777777', $nuevo->nif);
        $this->assertSame('Cádiz', $nuevo->ciudad);
        $this->assertSame($nuevo->id, Compra::firstOrFail()->proveedor_id);
    }

    /**
     * FR-021: todo o nada. Si la compra no es válida, no puede quedar el proveedor suelto.
     */
    public function test_si_la_compra_falla_no_queda_el_proveedor_creado(): void
    {
        [, , , $token] = $this->escenario();
        $proveedoresAntes = Proveedor::count();

        $respuesta = $this->postJson("/compras/documentos/{$token}/crear", [
            'crear_proveedor' => true,
            'proveedor_nuevo' => ['nombre' => 'Ferretería Fantasma'],
            'fecha' => '2026-03-14',
            'lineas' => [
                // cantidad 0 viola `gt:0`
                ['articulo_id' => null, 'concepto' => 'X', 'cantidad' => 0, 'precio_unitario' => 10, 'tipo_impositivo' => 21],
            ],
        ]);

        $respuesta->assertStatus(422);
        $this->assertSame($proveedoresAntes, Proveedor::count(), 'Un alta parcial dejaría basura en el catálogo.');
        $this->assertDatabaseCount('compras', 0);
    }

    /**
     * SC-006: descartar no crea absolutamente nada.
     */
    public function test_descartar_la_propuesta_no_crea_ningun_proveedor(): void
    {
        [, , , $token] = $this->escenario();
        $proveedoresAntes = Proveedor::count();

        $this->deleteJson("/compras/documentos/{$token}")->assertOk();

        $this->assertSame($proveedoresAntes, Proveedor::count());
        $this->assertDatabaseCount('compras', 0);
    }

    /**
     * FR-031: el duplicado avisa, pero no le quita la decisión al usuario.
     */
    public function test_un_duplicado_responde_409_y_se_puede_confirmar_igualmente(): void
    {
        [$tenant, , $proveedor, $token] = $this->escenario();

        Compra::factory()->create([
            'tenant_id' => $tenant->id,
            'proveedor_id' => $proveedor->id,
            'numero_documento' => 'F-2026/1183',
            'fecha' => '2026-03-14',
        ]);

        $respuesta = $this->postJson("/compras/documentos/{$token}/crear", $this->carga($proveedor));

        $respuesta->assertStatus(409);
        $respuesta->assertJsonPath('codigo', 'posible_duplicado');
        $this->assertNotNull($respuesta->json('compra_existente.id'));
        $this->assertDatabaseCount('compras', 1);

        // El usuario insiste: se crea igual.
        $this->postJson("/compras/documentos/{$token}/crear", $this->carga($proveedor, ['confirmar_duplicado' => true]))
            ->assertStatus(201);

        $this->assertDatabaseCount('compras', 2);
    }

    /**
     * FR-026: una compra importada se integra con el control de stock igual que una manual —
     * usando `RegistroCompra` tal cual, sin lógica nueva.
     */
    public function test_confirmar_una_compra_importada_sube_el_stock_y_anularla_lo_revierte(): void
    {
        [$tenant, , $proveedor, $token] = $this->escenario();

        $articulo = Articulo::factory()->producto()->create([
            'tenant_id' => $tenant->id,
            'gestion_stock' => true,
            'stock_actual' => 100,
        ]);

        $this->postJson("/compras/documentos/{$token}/crear", $this->carga($proveedor, [
            'lineas' => [
                ['articulo_id' => $articulo->id, 'concepto' => 'Tornillo M6', 'unidad' => 'ud', 'cantidad' => 25, 'precio_unitario' => 1, 'tipo_impositivo' => 21],
            ],
        ]))->assertStatus(201);

        $compra = Compra::firstOrFail();

        // `stock_actual` es decimal:4: se compara el valor, no su representación.
        // En borrador el inventario no se toca.
        $this->assertEqualsWithDelta(100, (float) $articulo->fresh()->stock_actual, 0.0001);

        $this->post("/compras/{$compra->id}/confirmar");
        $this->assertEqualsWithDelta(125, (float) $articulo->fresh()->stock_actual, 0.0001);

        $this->post("/compras/{$compra->id}/anular");
        $this->assertEqualsWithDelta(100, (float) $articulo->fresh()->stock_actual, 0.0001);
    }

    public function test_rechaza_crear_sin_proveedor_resuelto(): void
    {
        [, , , $token] = $this->escenario();

        $respuesta = $this->postJson("/compras/documentos/{$token}/crear", [
            'crear_proveedor' => false,
            'fecha' => '2026-03-14',
            'lineas' => [
                ['articulo_id' => null, 'concepto' => 'X', 'cantidad' => 1, 'precio_unitario' => 1, 'tipo_impositivo' => 21],
            ],
        ]);

        $respuesta->assertStatus(422);
        $respuesta->assertJsonValidationErrors('proveedor_id');
        $this->assertDatabaseCount('compras', 0);
    }
}
