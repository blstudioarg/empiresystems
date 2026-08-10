<?php

namespace Tests\Feature\SuperAdmin;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Tests\Concerns\GestionaRolesDeTenant;
use Tests\TestCase;

/**
 * US1 (037-super-admin-panel-aislado): el Super Admin no puede entrar a pantallas del área de
 * empresa. Test-first (Principio IV): estos casos deben fallar antes de crear el middleware
 * `BloquearSuperAdminAreaTenant` (T011-T015).
 */
class SuperAdminAreaTenantBloqueadaTest extends TestCase
{
    use GestionaRolesDeTenant, RefreshDatabase;

    /**
     * @return list<string>
     */
    private function rutasAreaTenant(): array
    {
        return ['/clientes', '/facturas', '/articulos', '/configuracion', '/logs', '/'];
    }

    public function test_navegacion_a_pantallas_de_empresa_redirige_a_la_home_del_panel_con_aviso(): void
    {
        $superAdmin = User::factory()->superAdmin()->create(['password' => bcrypt('secret123')]);
        $this->loginAs($superAdmin);

        foreach ($this->rutasAreaTenant() as $ruta) {
            $response = $this->get($ruta);

            $response->assertRedirect(route('super_admin.home'));
            $response->assertSessionHas('warning');
        }
    }

    public function test_descarga_de_documento_de_empresa_queda_bloqueada(): void
    {
        $tenant = Tenant::factory()->create();
        $factura = \App\Models\Factura::factory()->create(['tenant_id' => $tenant->id]);

        $superAdmin = User::factory()->superAdmin()->create(['password' => bcrypt('secret123')]);
        $this->loginAs($superAdmin);

        $response = $this->get('/facturas/'.$factura->id.'/pdf');

        $response->assertRedirect(route('super_admin.home'));
    }

    public function test_peticion_ajax_a_pantalla_de_empresa_responde_403_json(): void
    {
        $superAdmin = User::factory()->superAdmin()->create(['password' => bcrypt('secret123')]);
        $this->loginAs($superAdmin);

        $response = $this->get('/clientes', ['Accept' => 'application/json']);

        $response->assertStatus(403);
        $response->assertJsonStructure(['message']);
    }

    public function test_post_de_escritura_de_empresa_se_rechaza_sin_modificar_datos(): void
    {
        // El front del proyecto envía todas las escrituras por AJAX con Accept: application/json
        // (convención del proyecto, ver public/js/plugins-init/clientes-modal.init.js); es la
        // petición "en segundo plano" del edge case de spec.md, no una navegación de formulario.
        $superAdmin = User::factory()->superAdmin()->create(['password' => bcrypt('secret123')]);
        $this->loginAs($superAdmin);

        $response = $this->post('/clientes', ['nombre' => 'Intento bloqueado'], ['Accept' => 'application/json']);

        $response->assertStatus(403);
        $this->assertDatabaseCount('clientes', 0);
    }

    public function test_allowlist_perfil_logout_y_localidades_siguen_accesibles(): void
    {
        $provincia = \App\Models\Provincia::create(['id' => 'M', 'nombre' => 'Madrid']);

        $superAdmin = User::factory()->superAdmin()->create(['password' => bcrypt('secret123')]);
        $this->loginAs($superAdmin);

        $this->get('/perfil')->assertOk();
        $this->get('/localidades?provincia_id='.$provincia->id)->assertOk();

        $response = $this->post('/logout');
        $response->assertRedirect(route('login'));
    }

    public function test_usuario_de_tenant_a_accede_con_normalidad_a_su_propio_dominio(): void
    {
        $this->sembrarPermisos();
        $tenant = Tenant::factory()->create();
        $rol = $this->crearRol($tenant, 'Administrador', \App\Support\CatalogoPermisos::claves());
        $usuario = $this->usuarioConRol($tenant, $rol);

        $this->loginAs($usuario);

        $this->get('/clientes')->assertOk();
        $this->get('/facturas')->assertOk();
        $this->get('http://localhost/super_admin/tenants')->assertForbidden();
    }

    public function test_usuario_de_tenant_b_accede_con_normalidad_a_su_propio_dominio_y_no_ve_datos_de_a(): void
    {
        $this->sembrarPermisos();
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        \App\Models\Cliente::factory()->create(['tenant_id' => $tenantA->id, 'nombre' => 'Cliente de A']);
        \App\Models\Cliente::factory()->create(['tenant_id' => $tenantB->id, 'nombre' => 'Cliente de B']);

        $rol = $this->crearRol($tenantB, 'Administrador', \App\Support\CatalogoPermisos::claves());
        $usuarioB = $this->usuarioConRol($tenantB, $rol);

        $this->loginAs($usuarioB);

        $response = $this->get('/clientes', ['Accept' => 'application/json']);
        $response->assertOk();
        $response->assertJsonFragment(['nombre' => 'Cliente de B']);
        $response->assertJsonMissing(['nombre' => 'Cliente de A']);
    }

    public function test_sesion_de_super_admin_en_dominio_de_tenant_no_obtiene_la_pantalla_del_tenant(): void
    {
        $tenant = Tenant::factory()->create();
        $superAdmin = User::factory()->superAdmin()->create(['password' => bcrypt('secret123')]);

        $this->actingAs($superAdmin);
        $response = $this->get('http://'.$this->domainFor($tenant).'/clientes');

        $response->assertStatus(302);
        $response->assertRedirect(route('super_admin.home'));
    }

    public function test_toda_ruta_del_area_de_tenant_lleva_el_middleware_de_bloqueo(): void
    {
        $rutasSinBloqueo = collect(Route::getRoutes())
            ->filter(function ($route) {
                $middleware = $route->gatherMiddleware();

                return in_array('tenant.context', $middleware, true)
                    && in_array('auth', $middleware, true)
                    && ! in_array('super_admin', $middleware, true);
            })
            ->reject(fn ($route) => in_array('sin_super_admin', $route->gatherMiddleware(), true))
            ->map(fn ($route) => $route->getName() ?? $route->uri())
            ->values();

        $this->assertTrue(
            $rutasSinBloqueo->isEmpty(),
            'Rutas del área de tenant sin middleware sin_super_admin: '.$rutasSinBloqueo->implode(', ')
        );
    }

    public function test_intento_bloqueado_queda_registrado_en_el_log_de_aplicacion_y_no_en_logs_actividad(): void
    {
        Log::spy();

        $superAdmin = User::factory()->superAdmin()->create(['password' => bcrypt('secret123')]);
        $this->loginAs($superAdmin);

        $this->get('/clientes');

        Log::shouldHaveReceived('warning')
            ->once()
            ->withArgs(function (string $mensaje, array $contexto) use ($superAdmin) {
                return $mensaje === 'super_admin.acceso_area_tenant_bloqueado'
                    && $contexto['usuario_id'] === $superAdmin->id
                    && array_key_exists('ip', $contexto)
                    && array_key_exists('user_agent', $contexto);
            });

        $this->assertDatabaseCount('logs_actividad', 0);
    }
}
