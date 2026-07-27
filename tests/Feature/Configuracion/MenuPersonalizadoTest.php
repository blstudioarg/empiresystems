<?php

namespace Tests\Feature\Configuracion;

use App\Models\Configuracion;
use App\Models\LogActividad;
use App\Models\Tenant;
use App\Models\User;
use App\Support\MenuTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Endpoints de la tab "Menú" (contracts/configuracion-menu.md): guardar (US1 renombrar, US2
 * reordenar) y restaurar (US3). FR-004 a FR-018, FR-022.
 */
class MenuPersonalizadoTest extends TestCase
{
    use RefreshDatabase;

    private function admin(Tenant $tenant): User
    {
        return User::factory()->admin()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
    }

    // -- Renombrar (US1) ----------------------------------------------------------------------

    public function test_guardar_etiquetas_persiste_la_fila_y_se_refleja_en_la_estructura(): void
    {
        $tenant = Tenant::factory()->create();
        $this->loginAs($this->admin($tenant));

        $this->putJson(route('configuracion.menu.update'), [
            'etiquetas' => ['clientes' => 'Pacientes', 'cartera-clientes' => 'Mis pacientes'],
        ])->assertOk()->assertJson(['message' => 'Menú guardado correctamente.']);

        $this->assertDatabaseHas('configuraciones', [
            'tenant_id' => $tenant->id,
            'clave' => MenuTenant::CLAVE,
        ]);

        $estructura = MenuTenant::estructura($tenant->id);
        $grupo = collect($estructura)->firstWhere('clave', 'clientes');

        $this->assertSame('Pacientes', $grupo['etiqueta']);
        $this->assertSame('Mis pacientes', $grupo['hijos'][0]['etiqueta']);
    }

    public function test_nombre_vacio_devuelve_422_con_la_clave_del_elemento(): void
    {
        $tenant = Tenant::factory()->create();
        $this->loginAs($this->admin($tenant));

        $this->putJson(route('configuracion.menu.update'), [
            'etiquetas' => ['clientes' => '   '],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['etiquetas.clientes']);

        $this->assertDatabaseMissing('configuraciones', [
            'tenant_id' => $tenant->id,
            'clave' => MenuTenant::CLAVE,
        ]);
    }

    public function test_nombre_de_41_caracteres_devuelve_422(): void
    {
        $tenant = Tenant::factory()->create();
        $this->loginAs($this->admin($tenant));

        $this->putJson(route('configuracion.menu.update'), [
            'etiquetas' => ['clientes' => str_repeat('a', 41)],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['etiquetas.clientes']);
    }

    public function test_nombre_de_40_caracteres_se_acepta(): void
    {
        $tenant = Tenant::factory()->create();
        $this->loginAs($this->admin($tenant));

        $this->putJson(route('configuracion.menu.update'), [
            'etiquetas' => ['clientes' => str_repeat('a', 40)],
        ])->assertOk();
    }

    public function test_clave_desconocida_en_etiquetas_se_descarta_sin_error(): void
    {
        $tenant = Tenant::factory()->create();
        $this->loginAs($this->admin($tenant));

        $this->putJson(route('configuracion.menu.update'), [
            'etiquetas' => ['clave-fantasma' => 'Lo que sea'],
        ])->assertOk();

        // Nada que guardar (la única clave enviada no existe) ⇒ no se crea fila.
        $this->assertDatabaseMissing('configuraciones', [
            'tenant_id' => $tenant->id,
            'clave' => MenuTenant::CLAVE,
        ]);
    }

    public function test_una_etiqueta_identica_al_valor_por_defecto_no_se_persiste(): void
    {
        $tenant = Tenant::factory()->create();
        $this->loginAs($this->admin($tenant));

        $this->putJson(route('configuracion.menu.update'), [
            'etiquetas' => ['clientes' => 'Clientes'],
        ])->assertOk();

        $this->assertDatabaseMissing('configuraciones', [
            'tenant_id' => $tenant->id,
            'clave' => MenuTenant::CLAVE,
        ]);
    }

    public function test_usuario_sin_ver_configuracion_recibe_403(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $this->loginAs($user);

        $this->putJson(route('configuracion.menu.update'), [
            'etiquetas' => ['clientes' => 'Pacientes'],
        ])->assertForbidden();
    }

    public function test_guardar_registra_actividad(): void
    {
        $tenant = Tenant::factory()->create();
        $this->loginAs($this->admin($tenant));

        $this->putJson(route('configuracion.menu.update'), [
            'etiquetas' => ['clientes' => 'Pacientes'],
        ])->assertOk();

        $this->assertTrue(
            LogActividad::query()
                ->where('tenant_id', $tenant->id)
                ->where('descripcion', 'like', '%menú lateral%')
                ->exists()
        );
    }

    // -- Reordenar (US2) -----------------------------------------------------------------------

    public function test_guardar_orden_se_persiste_y_se_refleja_en_la_estructura(): void
    {
        $tenant = Tenant::factory()->create();
        $this->loginAs($this->admin($tenant));

        $this->putJson(route('configuracion.menu.update'), [
            'orden' => ['_raiz' => ['facturas', 'clientes']],
        ])->assertOk();

        $claves = array_column(MenuTenant::estructura($tenant->id), 'clave');

        $this->assertSame('facturas', $claves[0]);
        $this->assertSame('clientes', $claves[1]);
    }

    public function test_orden_parcial_deja_el_resto_al_final_de_su_nivel(): void
    {
        $tenant = Tenant::factory()->create();
        $this->loginAs($this->admin($tenant));

        $this->putJson(route('configuracion.menu.update'), [
            'orden' => ['_raiz' => ['usuarios']],
        ])->assertOk();

        $claves = array_column(MenuTenant::estructura($tenant->id), 'clave');

        $this->assertSame('usuarios', $claves[0]);
        $this->assertCount(10, $claves);
    }

    public function test_claves_desconocidas_en_orden_se_descartan(): void
    {
        $tenant = Tenant::factory()->create();
        $this->loginAs($this->admin($tenant));

        $this->putJson(route('configuracion.menu.update'), [
            'orden' => ['_raiz' => ['clave-fantasma', 'facturas']],
        ])->assertOk();

        $claves = array_column(MenuTenant::estructura($tenant->id), 'clave');

        $this->assertNotContains('clave-fantasma', $claves);
        $this->assertSame('facturas', $claves[0]);
    }

    public function test_el_servidor_reconstruye_desde_el_catalogo_aunque_la_peticion_llegue_incompleta(): void
    {
        $tenant = Tenant::factory()->create();
        $this->loginAs($this->admin($tenant));

        // Ni etiquetas ni orden completos: el servidor no confía en la petición (D4), la
        // estructura resultante sigue teniendo los 36 elementos del catálogo.
        $this->putJson(route('configuracion.menu.update'), [
            'orden' => ['control-fichaje' => ['alertas']],
        ])->assertOk();

        $estructura = MenuTenant::estructura($tenant->id);
        $totalHijos = array_sum(array_map(fn ($g) => count($g['hijos']), $estructura));

        $this->assertCount(10, $estructura);
        $this->assertSame(26, $totalHijos);
    }

    // -- Restaurar (US3) -----------------------------------------------------------------------

    public function test_restaurar_borra_la_fila_de_configuracion(): void
    {
        $tenant = Tenant::factory()->create();
        $this->loginAs($this->admin($tenant));

        $this->putJson(route('configuracion.menu.update'), ['etiquetas' => ['clientes' => 'Pacientes']])->assertOk();
        $this->assertDatabaseHas('configuraciones', ['tenant_id' => $tenant->id, 'clave' => MenuTenant::CLAVE]);

        $this->deleteJson(route('configuracion.menu.restaurar'))
            ->assertOk()
            ->assertJson(['message' => 'Menú restaurado a los valores por defecto.']);

        $this->assertDatabaseMissing('configuraciones', ['tenant_id' => $tenant->id, 'clave' => MenuTenant::CLAVE]);
    }

    public function test_restaurar_un_tenant_nunca_personalizado_responde_200_sin_error(): void
    {
        $tenant = Tenant::factory()->create();
        $this->loginAs($this->admin($tenant));

        $this->deleteJson(route('configuracion.menu.restaurar'))->assertOk();
    }

    public function test_tras_restaurar_la_estructura_es_identica_al_catalogo(): void
    {
        $tenant = Tenant::factory()->create();
        $this->loginAs($this->admin($tenant));

        $this->putJson(route('configuracion.menu.update'), [
            'etiquetas' => ['clientes' => 'Pacientes'],
            'orden' => ['_raiz' => ['facturas', 'clientes']],
        ])->assertOk();

        $response = $this->deleteJson(route('configuracion.menu.restaurar'))->assertOk();

        $estructuraRespuesta = $response->json('estructura');
        $estructuraServidor = MenuTenant::estructura($tenant->id);

        $this->assertSame('Clientes', collect($estructuraServidor)->firstWhere('clave', 'clientes')['etiqueta']);
        $this->assertSame('inicio', $estructuraServidor[0]['clave']);
        $this->assertSame($estructuraServidor, $estructuraRespuesta);
    }

    public function test_usuario_sin_ver_configuracion_recibe_403_al_restaurar(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $this->loginAs($user);

        $this->deleteJson(route('configuracion.menu.restaurar'))->assertForbidden();
    }

    public function test_restaurar_registra_actividad(): void
    {
        $tenant = Tenant::factory()->create();
        $this->loginAs($this->admin($tenant));

        $this->deleteJson(route('configuracion.menu.restaurar'))->assertOk();

        $this->assertTrue(
            LogActividad::query()
                ->where('tenant_id', $tenant->id)
                ->where('descripcion', 'like', '%menú lateral%')
                ->exists()
        );
    }

    // -- Aislamiento entre las dos rutas (defensa en profundidad, complementa MenuAislamientoTenantTest) --

    public function test_guardar_no_afecta_a_otro_tenant(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $this->loginAs($this->admin($tenantA));

        $this->putJson(route('configuracion.menu.update'), ['etiquetas' => ['clientes' => 'Pacientes']])->assertOk();

        $this->assertDatabaseMissing('configuraciones', ['tenant_id' => $tenantB->id, 'clave' => MenuTenant::CLAVE]);
        $this->assertSame('Clientes', collect(MenuTenant::estructura($tenantB->id))->firstWhere('clave', 'clientes')['etiqueta']);
    }
}
