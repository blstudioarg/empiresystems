<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Support\ConfigPos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\GestionaRolesDeTenant;
use Tests\TestCase;

class RutasPermisosTest extends TestCase
{
    use GestionaRolesDeTenant, RefreshDatabase;

    /**
     * Permiso → ruta índice de su sección. `ver-dashboard` (landing) y `ver-roles` (validado en
     * RolesTest) se prueban aparte.
     *
     * @return array<string, string>
     */
    private function mapaRutas(): array
    {
        return [
            // Control de fichaje desglosado (doc 09, Cambios 1 y 5). `ver-mi-jornada` NO entra aquí:
            // /mi-jornada exige además un perfil de miembro (abort_if), así que no cumple el patrón
            // 403-sin-permiso / 200-con-permiso puro; su gate se cubre por separado.
            'ver-fichar' => '/fichajes',
            'ver-jornada' => '/jornada',
            'ver-calendario' => '/calendario',
            'ver-miembros' => '/miembros-equipo',
            'ver-horarios' => '/horarios',
            'ver-alertas' => '/alertas',
            'ver-clientes' => '/clientes',
            'ver-articulos' => '/articulos',
            'ver-stock' => '/stock',
            'ver-proveedores' => '/proveedores',
            'ver-compras' => '/compras',
            'ver-facturas' => '/facturas',
            'ver-facturas-crear' => '/facturas/crear',
            'ver-pos' => '/pos',
            'ver-pos-crear' => '/pos/crear',
            // Módulo de hostelería (feature 038). Estas dos rutas llevan además el middleware
            // `modulo.hosteleria`, así que el test activa el módulo en el tenant antes del bucle;
            // el caso "permiso sí, módulo apagado" se cubre en ModuloApagadoTest.
            'ver-pos-sala' => '/pos/sala',
            'ver-pos-opciones' => '/pos/opciones',
            'ver-archivos' => '/archivos',
            'ver-campanas' => '/campanas',
            'ver-campanas-crear' => '/campanas/crear',
            'ver-plantillas-email' => '/plantillas-email',
            'ver-usuarios' => '/usuarios',
            'ver-logs' => '/logs',
        ];
    }

    public function test_sin_el_permiso_la_ruta_responde_403_y_con_el_permiso_200(): void
    {
        $this->sembrarPermisos();
        $tenant = Tenant::factory()->create();
        // Las rutas del módulo de hostelería solo existen con el módulo activo; aquí se prueba el
        // eje "permiso", no el eje "módulo" (feature 038, research.md D6).
        ConfigPos::guardar($tenant->id, ['hosteleria_activo' => true, 'opciones_activo' => true]);

        foreach ($this->mapaRutas() as $permiso => $ruta) {
            $rol = $this->crearRol($tenant, "Rol {$permiso}", [$permiso]);
            $conPermiso = $this->usuarioConRol($tenant, $rol);
            $sinPermiso = $this->usuarioConRol($tenant, $this->crearRol($tenant, "Otro {$permiso}", ['ver-dashboard']));

            $this->loginAs($sinPermiso);
            $this->get($ruta)->assertForbidden();
            $this->getJson($ruta)->assertForbidden();
            $this->post('/logout');

            $this->loginAs($conPermiso);
            $this->get($ruta)->assertOk();
            $this->post('/logout');
        }
    }

    public function test_usuario_sin_rol_recibe_403_en_gestion_y_solo_200_en_perfil(): void
    {
        $this->sembrarPermisos();
        $tenant = Tenant::factory()->create();
        $usuario = $this->usuarioConRol($tenant, null);

        $this->loginAs($usuario);

        $this->get('/clientes')->assertForbidden();
        $this->get('/facturas')->assertForbidden();

        // Fichar y Mi jornada ya no son universales: sin ver-fichar/ver-mi-jornada dan 403
        // (doc 09, Cambio 5). Perfil es la única sección universal.
        $this->get('/fichajes')->assertForbidden();
        $this->get('/mi-jornada')->assertForbidden();
        $this->get('/perfil')->assertOk();
    }

    public function test_landing_sin_ver_dashboard_redirige_a_la_primera_seccion_permitida(): void
    {
        $this->sembrarPermisos();
        $tenant = Tenant::factory()->create();
        // Solo ver-clientes: sin ver-dashboard ni fichaje, la primera sección permitida es Clientes.
        $usuario = $this->usuarioConRol($tenant, $this->crearRol($tenant, 'Ventas', ['ver-clientes']));

        $this->loginAs($usuario);

        $this->get('/')->assertRedirect(route('clientes.index'));
    }

    public function test_landing_prefiere_fichar_cuando_esta_permitido(): void
    {
        $this->sembrarPermisos();
        $tenant = Tenant::factory()->create();
        // ver-fichar precede a ver-clientes en el orden de menú → aterriza en Fichar.
        $usuario = $this->usuarioConRol($tenant, $this->crearRol($tenant, 'Operario', ['ver-fichar', 'ver-clientes']));

        $this->loginAs($usuario);

        $this->get('/')->assertRedirect(route('fichajes.index'));
    }

    public function test_landing_sin_ningun_permiso_cae_en_perfil(): void
    {
        $this->sembrarPermisos();
        $tenant = Tenant::factory()->create();
        $usuario = $this->usuarioConRol($tenant, null);

        $this->loginAs($usuario);

        $this->get('/')->assertRedirect(route('profile.show'));
    }

    public function test_landing_con_ver_dashboard_responde_200(): void
    {
        $this->sembrarPermisos();
        $tenant = Tenant::factory()->create();
        $usuario = $this->usuarioConRol($tenant, $this->crearRol($tenant, 'Gestor', ['ver-dashboard']));

        $this->loginAs($usuario);

        $this->get('/')->assertOk();
    }
}
