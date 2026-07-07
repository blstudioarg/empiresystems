<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Support\CatalogoPermisos;
use App\Support\ProvisionadorRoles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\GestionaRolesDeTenant;
use Tests\TestCase;

class RegistroRolDefectoTest extends TestCase
{
    use GestionaRolesDeTenant, RefreshDatabase;

    private function registrar(Tenant $tenant, string $email): void
    {
        $this->actingOnDomain($this->domainFor($tenant));

        $this->post('/registro', [
            'name' => 'Nuevo Usuario',
            'email' => $email,
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);
    }

    public function test_usuario_registrado_recibe_el_rol_por_defecto_del_tenant(): void
    {
        $this->sembrarPermisos();
        $tenant = Tenant::factory()->create();
        $this->crearRol($tenant, ProvisionadorRoles::ROL_USUARIO, CatalogoPermisos::clavesUsuarioBase(), esDefecto: true);

        $this->registrar($tenant, 'nuevo@destino.test');

        $usuario = User::where('email', 'nuevo@destino.test')->firstOrFail();

        $this->enTeam($tenant, function () use ($usuario) {
            $this->assertTrue($usuario->fresh()->hasRole(ProvisionadorRoles::ROL_USUARIO));
        });
    }

    public function test_sin_rol_por_defecto_el_usuario_queda_sin_rol_y_el_registro_es_exitoso(): void
    {
        $this->sembrarPermisos();
        $tenant = Tenant::factory()->create();

        $this->registrar($tenant, 'nuevo@destino.test');

        $usuario = User::where('email', 'nuevo@destino.test')->first();

        $this->assertNotNull($usuario);
        $this->enTeam($tenant, function () use ($usuario) {
            $this->assertCount(0, $usuario->fresh()->roles);
        });
    }

    public function test_cada_registro_recibe_el_rol_por_defecto_de_su_propio_tenant(): void
    {
        $this->sembrarPermisos();
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $this->crearRol($tenantA, 'Usuario A', ['ver-clientes'], esDefecto: true);
        $this->crearRol($tenantB, 'Usuario B', ['ver-facturas'], esDefecto: true);

        $this->registrar($tenantA, 'usuario-a@destino.test');
        $this->registrar($tenantB, 'usuario-b@destino.test');

        $usuarioA = User::where('email', 'usuario-a@destino.test')->firstOrFail();
        $usuarioB = User::where('email', 'usuario-b@destino.test')->firstOrFail();

        $this->enTeam($tenantA, fn () => $this->assertTrue($usuarioA->fresh()->hasRole('Usuario A')));
        $this->enTeam($tenantB, fn () => $this->assertTrue($usuarioB->fresh()->hasRole('Usuario B')));
    }
}
