<?php

namespace Tests\Feature\Configuracion;

use App\Enums\EstadoAlerta;
use App\Enums\UserRole;
use App\Models\Alerta;
use App\Models\Tenant;
use App\Models\User;
use App\Support\CatalogoMenu;
use App\Support\CatalogoPermisos;
use App\Support\MenuTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\GestionaRolesDeTenant;
use Tests\TestCase;

/**
 * FR-011, FR-014, SC-003, SC-005: para varios roles, el conjunto de entradas visibles en el
 * sidebar dirigido por catálogo debe ser idéntico al que resultaría de filtrar el catálogo por
 * los permisos del rol — ni una entrada de más ni de menos — y el badge de alertas debe seguir
 * apareciendo aunque se renombre "Alertas" (D6.2). Se afirma sobre atributos `href` (marcadores
 * de HTML), nunca sobre texto plano que también pueda aparecer en el panel de ayuda.
 */
class MenuSidebarRenderTest extends TestCase
{
    use GestionaRolesDeTenant, RefreshDatabase;

    /**
     * Recorta el bloque del menú lateral (`<ul class="metismenu">` hasta el botón de ayuda) para
     * que las aserciones de `href` no choquen con enlaces repetidos fuera del menú — el caso
     * conocido es "Inicio" (`dashboard`), que resuelve a la URL raíz y coincide con el `href` del
     * logo del nav-header.
     */
    private function extraerMenu(string $html): string
    {
        $inicio = strpos($html, '<ul class="metismenu" id="menu">');
        $fin = strpos($html, 'class="help-desk');

        return substr($html, $inicio, $fin - $inicio);
    }

    /**
     * Recorre todo el catálogo (elementos con ruta propia, incluidos los grupos sin hijos) y
     * comprueba que su `href` aparece en el menú si y solo si el permiso declarado está entre los
     * del rol (o no declara permiso).
     *
     * @param  list<string>  $permisosRol
     */
    private function assertMenuCoincideConPermisos(User $usuario, array $permisosRol): void
    {
        $this->loginAs($usuario);
        $menu = $this->extraerMenu($this->get('/perfil')->assertOk()->getContent());

        foreach (CatalogoMenu::planos() as $clave => $elemento) {
            if ($elemento['ruta'] === null) {
                continue; // grupo derivado, sin ruta propia: su visibilidad se prueba vía sus hijos
            }

            $visible = $elemento['permiso'] === null || in_array($elemento['permiso'], $permisosRol, true);
            $href = 'href="'.route($elemento['ruta']).'"';

            if ($visible) {
                $this->assertStringContainsString($href, $menu, "Se esperaba ver la entrada '{$clave}' ({$elemento['ruta']}).");
            } else {
                $this->assertStringNotContainsString($href, $menu, "No se esperaba ver la entrada '{$clave}' ({$elemento['ruta']}).");
            }
        }
    }

    public function test_administrador_con_todo_el_catalogo_ve_todas_las_entradas(): void
    {
        $this->sembrarPermisos();
        $tenant = Tenant::factory()->create();
        $permisos = CatalogoPermisos::claves();
        $rol = $this->crearRol($tenant, 'Administrador', $permisos);
        $usuario = $this->usuarioConRol($tenant, $rol, ['rol' => UserRole::Admin]);

        $this->assertMenuCoincideConPermisos($usuario, $permisos);
    }

    public function test_rol_acotado_sin_ver_facturas_no_ve_facturas_pero_ve_el_resto(): void
    {
        $this->sembrarPermisos();
        $tenant = Tenant::factory()->create();
        $permisos = array_values(array_diff(CatalogoPermisos::claves(), ['ver-facturas', 'ver-facturas-crear']));
        $rol = $this->crearRol($tenant, 'Sin facturas', $permisos);
        $usuario = $this->usuarioConRol($tenant, $rol);

        $this->assertMenuCoincideConPermisos($usuario, $permisos);
    }

    public function test_rol_minimo_solo_ve_sus_entradas_permitidas(): void
    {
        $this->sembrarPermisos();
        $tenant = Tenant::factory()->create();
        $permisos = ['ver-clientes'];
        $rol = $this->crearRol($tenant, 'Mínimo', $permisos);
        $usuario = $this->usuarioConRol($tenant, $rol);

        $this->assertMenuCoincideConPermisos($usuario, $permisos);
    }

    public function test_el_badge_de_alertas_sigue_apareciendo_aunque_se_renombre_la_entrada(): void
    {
        $this->sembrarPermisos();
        $tenant = Tenant::factory()->create();
        $rol = $this->crearRol($tenant, 'Con alertas', ['ver-alertas']);
        $usuario = $this->usuarioConRol($tenant, $rol);

        Alerta::factory()->count(2)->create(['tenant_id' => $tenant->id, 'estado' => EstadoAlerta::Nueva]);

        MenuTenant::guardar($tenant->id, ['alertas' => 'Notificaciones', 'control-fichaje' => 'Fichajes'], []);

        $this->loginAs($usuario);
        $html = $this->get('/perfil')->assertOk()->getContent();

        $this->assertStringContainsString('Notificaciones', $html);
        $this->assertStringNotContainsString('>Alertas<', $html);
        $this->assertStringContainsString('nav-icon-badge">2<', $html);
        $this->assertStringContainsString('nav-inline-badge">2<', $html);
    }

    public function test_el_sidebar_respeta_el_orden_personalizado_de_los_grupos(): void
    {
        $this->sembrarPermisos();
        $tenant = Tenant::factory()->create();
        $rol = $this->crearRol($tenant, 'Administrador', CatalogoPermisos::claves());
        $usuario = $this->usuarioConRol($tenant, $rol, ['rol' => UserRole::Admin]);

        MenuTenant::guardar($tenant->id, [], ['_raiz' => ['facturas', 'clientes', 'inicio']]);

        $this->loginAs($usuario);
        $menu = $this->extraerMenu($this->get('/perfil')->assertOk()->getContent());

        $posicionFacturas = strpos($menu, 'href="'.route('facturas.index').'"');
        $posicionClientes = strpos($menu, 'href="'.route('clientes.index').'"');
        $posicionInicio = strpos($menu, 'href="'.route('dashboard').'"');

        $this->assertNotFalse($posicionFacturas);
        $this->assertNotFalse($posicionClientes);
        $this->assertNotFalse($posicionInicio);
        $this->assertTrue($posicionFacturas < $posicionClientes, 'Facturas debe aparecer antes que Clientes.');
        $this->assertTrue($posicionClientes < $posicionInicio, 'Clientes debe aparecer antes que Inicio.');
    }
}
