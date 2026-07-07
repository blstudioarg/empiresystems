<?php

namespace Tests\Feature;

use App\Models\Tenant;
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
            'ver-jornada' => '/jornada',
            'ver-clientes' => '/clientes',
            'ver-articulos' => '/articulos',
            'ver-stock' => '/stock',
            'ver-proveedores' => '/proveedores',
            'ver-compras' => '/compras',
            'ver-facturas' => '/facturas',
            'ver-pos' => '/pos',
            'ver-archivos' => '/archivos',
            'ver-campanas' => '/campanas',
            'ver-plantillas-email' => '/plantillas-email',
            'ver-usuarios' => '/usuarios',
            'ver-logs' => '/logs',
            'ver-bancos' => '/bancos',
        ];
    }

    public function test_sin_el_permiso_la_ruta_responde_403_y_con_el_permiso_200(): void
    {
        $this->sembrarPermisos();
        $tenant = Tenant::factory()->create();

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

    public function test_usuario_sin_rol_recibe_403_en_gestion_y_200_en_secciones_personales(): void
    {
        $this->sembrarPermisos();
        $tenant = Tenant::factory()->create();
        $usuario = $this->usuarioConRol($tenant, null);

        $this->loginAs($usuario);

        $this->get('/clientes')->assertForbidden();
        $this->get('/facturas')->assertForbidden();

        $this->get('/fichajes')->assertOk();
        $this->get('/perfil')->assertOk();
    }

    public function test_landing_sin_ver_dashboard_redirige_a_mi_jornada(): void
    {
        $this->sembrarPermisos();
        $tenant = Tenant::factory()->create();
        $usuario = $this->usuarioConRol($tenant, $this->crearRol($tenant, 'Ventas', ['ver-clientes']));

        $this->loginAs($usuario);

        $this->get('/')->assertRedirect(route('mi-jornada.index'));
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
