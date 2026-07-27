<?php

namespace Tests\Feature\Profile;

use App\Models\Tenant;
use App\Models\User;
use App\Support\ProvisionadorRoles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileRolPermisosTest extends TestCase
{
    use RefreshDatabase;

    public function test_perfil_muestra_el_rol_spatie_real_y_no_el_legado(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'password' => bcrypt('secret123'),
        ]);

        // El factory asigna por defecto el rol Spatie "Usuario" (coincide con el legado). Se
        // reasigna a "Administrador" vía Spatie SIN tocar la columna legado `rol`, simulando el
        // desfase real que motiva esta historia.
        app(ProvisionadorRoles::class)->provisionarAdministrador($tenant, $user);

        $this->loginAs($user);

        $response = $this->get('/perfil');

        $response->assertOk();
        $response->assertSee('Administrador');
    }

    public function test_superadmin_ve_su_rol_en_el_perfil(): void
    {
        $user = User::factory()->superAdmin()->create([
            'password' => bcrypt('secret123'),
        ]);

        $this->loginAs($user);

        $response = $this->get('/perfil');

        $response->assertOk();
        $response->assertSee('Super Admin');
    }
}
