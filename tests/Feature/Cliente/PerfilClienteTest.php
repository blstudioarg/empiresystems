<?php

namespace Tests\Feature\Cliente;

use App\Models\Albaran;
use App\Models\Cliente;
use App\Models\Factura;
use App\Models\Oportunidad;
use App\Models\Pago;
use App\Models\Presupuesto;
use App\Models\Tenant;
use App\Models\User;
use App\Support\CatalogoPermisos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class PerfilClienteTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Deja al usuario con todos los permisos del catálogo excepto el indicado.
     */
    private function quitarPermiso(User $user, Tenant $tenant, string $permiso): void
    {
        $registrar = app(PermissionRegistrar::class);
        $anterior = $registrar->getPermissionsTeamId();
        $registrar->setPermissionsTeamId($tenant->getTenantKey());

        $user->syncRoles([]);
        $user->givePermissionTo(array_diff(CatalogoPermisos::claves(), [$permiso]));

        $registrar->setPermissionsTeamId($anterior);
        $registrar->forgetCachedPermissions();
    }

    // --- Acceso y aislamiento (Principio I) ---

    public function test_el_perfil_de_un_cliente_de_otro_tenant_devuelve_404(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $userA = User::factory()->create(['tenant_id' => $tenantA->id, 'password' => bcrypt('secret123')]);
        $clienteB = Cliente::factory()->create(['tenant_id' => $tenantB->id]);

        $this->loginAs($userA);

        $this->get("/clientes/{$clienteB->id}")->assertNotFound();
    }

    public function test_el_json_de_una_pestana_de_un_cliente_de_otro_tenant_devuelve_404(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $userA = User::factory()->create(['tenant_id' => $tenantA->id, 'password' => bcrypt('secret123')]);
        $clienteB = Cliente::factory()->create(['tenant_id' => $tenantB->id]);

        $this->loginAs($userA);

        $this->getJson("/clientes/{$clienteB->id}?recurso=facturas")->assertNotFound();
    }

    public function test_usuario_sin_ver_clientes_no_puede_acceder_al_perfil(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $cliente = Cliente::factory()->create(['tenant_id' => $tenant->id]);

        $this->quitarPermiso($user, $tenant, 'ver-clientes');
        $this->loginAs($user);

        $this->get("/clientes/{$cliente->id}")->assertForbidden();
    }

    // --- US1: datos generales + resumen financiero ---

    public function test_el_perfil_muestra_los_datos_generales_del_cliente(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $cliente = Cliente::factory()->create([
            'tenant_id' => $tenant->id,
            'nombre' => 'Cliente de Prueba',
            'razon_social' => 'Cliente de Prueba S.L.',
            'nif' => 'B12345678',
            'notas' => 'Nota interna de prueba',
        ]);

        $this->loginAs($user);

        $response = $this->get("/clientes/{$cliente->id}");

        $response->assertOk();
        // Los datos van al form editable vía window.clientePerfilState (JSON), no como texto suelto.
        $response->assertSee('Cliente de Prueba S.L.');
        $response->assertSee('B12345678');
        $response->assertSee('Nota interna de prueba');
    }

    public function test_el_resumen_financiero_coincide_con_las_facturas_del_cliente(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $cliente = Cliente::factory()->create(['tenant_id' => $tenant->id]);

        // Vencida sin cobrar: 121, vencimiento pasado.
        Factura::factory()->emitida()->create([
            'tenant_id' => $tenant->id, 'cliente_id' => $cliente->id,
            'total' => 121, 'fecha_vencimiento' => now()->subDays(10)->toDateString(),
        ]);

        // Cobrada por completo: 200.
        $cobrada = Factura::factory()->emitida()->create([
            'tenant_id' => $tenant->id, 'cliente_id' => $cliente->id,
            'total' => 200, 'fecha_vencimiento' => now()->addDays(30)->toDateString(),
        ]);
        Pago::factory()->create(['tenant_id' => $tenant->id, 'factura_id' => $cobrada->id, 'importe' => 200]);

        // Parcial: 100, cobrados 40, vencimiento futuro.
        $parcial = Factura::factory()->emitida()->create([
            'tenant_id' => $tenant->id, 'cliente_id' => $cliente->id,
            'total' => 100, 'fecha_vencimiento' => now()->addDays(30)->toDateString(),
        ]);
        Pago::factory()->create(['tenant_id' => $tenant->id, 'factura_id' => $parcial->id, 'importe' => 40]);

        $this->loginAs($user);

        $response = $this->get("/clientes/{$cliente->id}");

        $response->assertOk();

        $resumen = $response->viewData('resumen');

        $this->assertEquals(421, $resumen['total_facturado']);      // 121 + 200 + 100
        $this->assertEquals(181, $resumen['pendiente_cobro']);      // 121 + 0 + 60
        $this->assertEquals(1, $resumen['facturas_vencidas_cantidad']);
        $this->assertEquals(121, $resumen['facturas_vencidas_importe']);
        $this->assertEquals(140.33, $resumen['ticket_medio']);      // 421 / 3
    }

    public function test_los_graficos_reparten_el_importe_por_estado_de_cobro(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $cliente = Cliente::factory()->create(['tenant_id' => $tenant->id]);

        Factura::factory()->emitida()->create([
            'tenant_id' => $tenant->id, 'cliente_id' => $cliente->id,
            'total' => 300, 'fecha_vencimiento' => now()->subDays(5)->toDateString(),
        ]);
        $cobrada = Factura::factory()->emitida()->create([
            'tenant_id' => $tenant->id, 'cliente_id' => $cliente->id,
            'total' => 500, 'fecha_vencimiento' => now()->addDays(20)->toDateString(),
        ]);
        Pago::factory()->create(['tenant_id' => $tenant->id, 'factura_id' => $cobrada->id, 'importe' => 500]);

        $this->loginAs($user);

        $graficos = $this->get("/clientes/{$cliente->id}")->assertOk()->viewData('graficos');

        $porEtiqueta = collect($graficos['cobro'])->keyBy('etiqueta');

        $this->assertEquals(500, $porEtiqueta['Cobrado']['importe']);
        $this->assertEquals(300, $porEtiqueta['Vencido sin cobrar']['importe']);
        $this->assertEquals(0, $porEtiqueta['Pendiente al día']['importe']);
        $this->assertCount(6, $graficos['evolucion']);
    }

    public function test_cliente_sin_facturas_muestra_resumen_en_cero_sin_error(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $cliente = Cliente::factory()->create(['tenant_id' => $tenant->id]);

        $this->loginAs($user);

        $response = $this->get("/clientes/{$cliente->id}");

        $response->assertOk();
        $response->assertSee('Sin facturas registradas');
        $this->assertEquals(0, $response->viewData('resumen')['total_facturado']);
    }

    // --- US2: listados documentales (DataTables por AJAX) ---

    public function test_cada_pestana_solo_devuelve_los_documentos_del_cliente_correcto(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $cliente = Cliente::factory()->create(['tenant_id' => $tenant->id]);
        $otro = Cliente::factory()->create(['tenant_id' => $tenant->id]);

        Factura::factory()->emitida()->create(['tenant_id' => $tenant->id, 'cliente_id' => $cliente->id, 'numero_completo' => 'F-2026-0001']);
        Factura::factory()->emitida()->create(['tenant_id' => $tenant->id, 'cliente_id' => $otro->id, 'numero_completo' => 'F-2026-9999']);
        Presupuesto::factory()->create(['tenant_id' => $tenant->id, 'cliente_id' => $cliente->id, 'numero' => 'P-2026-0001']);
        Presupuesto::factory()->create(['tenant_id' => $tenant->id, 'cliente_id' => $otro->id, 'numero' => 'P-2026-9999']);
        Albaran::factory()->create(['tenant_id' => $tenant->id, 'cliente_id' => $cliente->id, 'numero' => 'A-2026-0001']);
        Albaran::factory()->create(['tenant_id' => $tenant->id, 'cliente_id' => $otro->id, 'numero' => 'A-2026-9999']);

        $this->loginAs($user);

        $facturas = $this->getJson("/clientes/{$cliente->id}?recurso=facturas")->assertOk()->json('data');
        $this->assertCount(1, $facturas);
        $this->assertSame('F-2026-0001', $facturas[0]['identificador']);

        $presupuestos = $this->getJson("/clientes/{$cliente->id}?recurso=presupuestos")->assertOk()->json('data');
        $this->assertCount(1, $presupuestos);
        $this->assertSame('P-2026-0001', $presupuestos[0]['identificador']);

        $albaranes = $this->getJson("/clientes/{$cliente->id}?recurso=albaranes")->assertOk()->json('data');
        $this->assertCount(1, $albaranes);
        $this->assertSame('A-2026-0001', $albaranes[0]['identificador']);
    }

    public function test_la_pestana_de_facturas_excluye_las_simplificadas(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $cliente = Cliente::factory()->create(['tenant_id' => $tenant->id]);

        Factura::factory()->emitida()->create(['tenant_id' => $tenant->id, 'cliente_id' => $cliente->id, 'numero_completo' => 'F-ORDINARIA']);
        Factura::factory()->emitida()->create([
            'tenant_id' => $tenant->id, 'cliente_id' => $cliente->id,
            'tipo' => 'simplificada', 'numero_completo' => 'S-TICKET',
        ]);

        $this->loginAs($user);

        $facturas = $this->getJson("/clientes/{$cliente->id}?recurso=facturas")->assertOk()->json('data');

        $this->assertCount(1, $facturas);
        $this->assertSame('F-ORDINARIA', $facturas[0]['identificador']);
    }

    public function test_el_ver_de_una_factura_apunta_al_pdf_no_a_la_edicion(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $cliente = Cliente::factory()->create(['tenant_id' => $tenant->id]);
        $factura = Factura::factory()->emitida()->create(['tenant_id' => $tenant->id, 'cliente_id' => $cliente->id]);

        $this->loginAs($user);

        $facturas = $this->getJson("/clientes/{$cliente->id}?recurso=facturas")->assertOk()->json('data');

        $this->assertSame(route('facturas.pdf', $factura), $facturas[0]['pdf_url']);
    }

    public function test_sin_permiso_de_facturas_no_hay_pestana_ni_datos_de_facturacion(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $cliente = Cliente::factory()->create(['tenant_id' => $tenant->id]);

        Factura::factory()->emitida()->create([
            'tenant_id' => $tenant->id, 'cliente_id' => $cliente->id, 'numero_completo' => 'F-2026-0042',
        ]);

        $this->quitarPermiso($user, $tenant, 'ver-facturas');
        $this->loginAs($user);

        $response = $this->get("/clientes/{$cliente->id}");
        $response->assertOk();
        $response->assertDontSee('id="tab-facturas-btn"', false);
        $this->assertNull($response->viewData('resumen'));

        // Y el endpoint JSON tampoco puede filtrar los datos.
        $this->getJson("/clientes/{$cliente->id}?recurso=facturas")->assertForbidden();
    }

    public function test_sin_permiso_de_oportunidades_el_endpoint_json_responde_403(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $cliente = Cliente::factory()->create(['tenant_id' => $tenant->id]);

        $this->quitarPermiso($user, $tenant, 'ver-oportunidades');
        $this->loginAs($user);

        $this->getJson("/clientes/{$cliente->id}?recurso=oportunidades")->assertForbidden();
    }

    public function test_un_recurso_desconocido_devuelve_404(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $cliente = Cliente::factory()->create(['tenant_id' => $tenant->id]);

        $this->loginAs($user);

        $this->getJson("/clientes/{$cliente->id}?recurso=inventado")->assertNotFound();
    }

    // --- US3: oportunidades y actividad ---

    public function test_la_pestana_de_oportunidades_lista_solo_las_del_cliente(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $cliente = Cliente::factory()->create(['tenant_id' => $tenant->id]);
        $otro = Cliente::factory()->create(['tenant_id' => $tenant->id]);

        Oportunidad::factory()->create([
            'tenant_id' => $tenant->id, 'cliente_id' => $cliente->id,
            'titulo' => 'Oportunidad correcta', 'importe_estimado' => 1234,
        ]);
        Oportunidad::factory()->create([
            'tenant_id' => $tenant->id, 'cliente_id' => $otro->id, 'titulo' => 'Oportunidad ajena',
        ]);

        $this->loginAs($user);

        $oportunidades = $this->getJson("/clientes/{$cliente->id}?recurso=oportunidades")->assertOk()->json('data');

        $this->assertCount(1, $oportunidades);
        $this->assertSame('Oportunidad correcta', $oportunidades[0]['titulo']);
        $this->assertEquals(1234, $oportunidades[0]['importe_orden']);
        $this->assertNotEmpty($oportunidades[0]['etapa_label']);
    }

    public function test_la_actividad_combina_los_cuatro_tipos_ordenados_por_fecha_descendente(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $cliente = Cliente::factory()->create(['tenant_id' => $tenant->id]);

        Factura::factory()->emitida()->create([
            'tenant_id' => $tenant->id, 'cliente_id' => $cliente->id,
            'numero_completo' => 'F-TIMELINE', 'fecha_expedicion' => now()->subDays(4)->toDateString(),
        ]);
        Presupuesto::factory()->create([
            'tenant_id' => $tenant->id, 'cliente_id' => $cliente->id,
            'numero' => 'P-TIMELINE', 'fecha_emision' => now()->subDays(3)->toDateString(),
        ]);
        Albaran::factory()->create([
            'tenant_id' => $tenant->id, 'cliente_id' => $cliente->id,
            'numero' => 'A-TIMELINE', 'fecha_entrega' => now()->subDays(2)->toDateString(),
        ]);
        Oportunidad::factory()->create([
            'tenant_id' => $tenant->id, 'cliente_id' => $cliente->id,
            'titulo' => 'Oportunidad Timeline', 'created_at' => now()->subDay(),
        ]);

        $this->loginAs($user);

        $actividad = $this->getJson("/clientes/{$cliente->id}?recurso=actividad")->assertOk()->json('data');

        $this->assertCount(4, $actividad);
        $this->assertSame(['Oportunidad', 'Albarán', 'Presupuesto', 'Factura'], array_column($actividad, 'tipo'));

        // Los documentos con PDF se marcan para abrirse en modal; el resto navega.
        $porTipo = collect($actividad)->keyBy('tipo');
        $this->assertTrue($porTipo['Factura']['es_pdf']);
        $this->assertTrue($porTipo['Presupuesto']['es_pdf']);
        $this->assertFalse($porTipo['Albarán']['es_pdf']);
        $this->assertFalse($porTipo['Oportunidad']['es_pdf']);
    }

    public function test_la_actividad_omite_los_tipos_sin_permiso(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $cliente = Cliente::factory()->create(['tenant_id' => $tenant->id]);

        Factura::factory()->emitida()->create([
            'tenant_id' => $tenant->id, 'cliente_id' => $cliente->id, 'numero_completo' => 'F-OCULTA',
        ]);
        Oportunidad::factory()->create([
            'tenant_id' => $tenant->id, 'cliente_id' => $cliente->id, 'titulo' => 'Oportunidad visible',
        ]);

        $this->quitarPermiso($user, $tenant, 'ver-facturas');
        $this->loginAs($user);

        $actividad = $this->getJson("/clientes/{$cliente->id}?recurso=actividad")->assertOk()->json('data');

        $this->assertSame(['Oportunidad'], array_column($actividad, 'tipo'));
    }

    public function test_cliente_sin_actividad_devuelve_una_coleccion_vacia(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $cliente = Cliente::factory()->create(['tenant_id' => $tenant->id]);

        $this->loginAs($user);

        $this->getJson("/clientes/{$cliente->id}?recurso=actividad")->assertOk()->assertJson(['data' => []]);
    }

    // --- Edición de datos generales desde el perfil ---

    public function test_se_pueden_editar_los_datos_generales_desde_el_perfil(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $cliente = Cliente::factory()->create([
            'tenant_id' => $tenant->id, 'tipo' => 'particular', 'nombre' => 'Nombre viejo',
        ]);

        $this->loginAs($user);

        $response = $this->putJson("/clientes/{$cliente->id}", [
            'tipo' => 'particular',
            'nombre' => 'Nombre nuevo',
            'pais' => 'ES',
            'email' => 'nuevo@ejemplo.test',
        ]);

        $response->assertOk();
        $this->assertSame('Nombre nuevo', $cliente->fresh()->nombre);
        $this->assertSame('nuevo@ejemplo.test', $cliente->fresh()->email);
    }

    public function test_editar_datos_generales_con_email_invalido_devuelve_422(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $cliente = Cliente::factory()->create(['tenant_id' => $tenant->id, 'tipo' => 'particular']);

        $this->loginAs($user);

        $this->putJson("/clientes/{$cliente->id}", [
            'tipo' => 'particular',
            'nombre' => 'Nombre',
            'pais' => 'ES',
            'email' => 'no-es-un-email',
        ])->assertStatus(422)->assertJsonValidationErrors('email');
    }

    // --- US4: accesos rápidos ---

    public function test_sin_permiso_de_creacion_el_acceso_rapido_correspondiente_no_se_muestra(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $cliente = Cliente::factory()->create(['tenant_id' => $tenant->id]);

        $this->quitarPermiso($user, $tenant, 'ver-facturas-crear');
        $this->loginAs($user);

        $response = $this->get("/clientes/{$cliente->id}");

        $response->assertOk();
        $response->assertDontSee('+ Factura');
        $response->assertSee('+ Presupuesto');
        $response->assertSee('+ Albarán');
        $response->assertSee('+ Oportunidad');
    }

    public function test_facturas_crear_con_cliente_id_de_un_cliente_eliminado_no_rompe_ni_crea_huerfano(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $cliente = Cliente::factory()->create(['tenant_id' => $tenant->id]);
        $clienteId = $cliente->id;
        $cliente->delete();

        $this->loginAs($user);

        $response = $this->get("/facturas/crear?cliente_id={$clienteId}");

        $response->assertOk();
        $response->assertViewHas('clientePreseleccionado', null);
    }
}
