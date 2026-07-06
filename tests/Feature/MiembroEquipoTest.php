<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Fichaje;
use App\Models\MiembroEquipo;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MiembroEquipoTest extends TestCase
{
    use RefreshDatabase;

    private function crearAdmin(Tenant $tenant): User
    {
        return User::factory()->create(['tenant_id' => $tenant->id, 'rol' => UserRole::Admin, 'password' => bcrypt('secret123')]);
    }

    public function test_admin_puede_crear_un_miembro_con_ubicacion_y_distancia_maxima(): void
    {
        $tenant = Tenant::factory()->create();
        $admin = $this->crearAdmin($tenant);
        $empleado = User::factory()->create(['tenant_id' => $tenant->id]);
        $this->loginAs($admin);

        $response = $this->post('/miembros-equipo', [
            'user_id' => $empleado->id,
            'puesto' => 'Comercial',
            'trabajo_direccion' => 'Calle Falsa 123',
            'trabajo_latitud' => 40.4168,
            'trabajo_longitud' => -3.7038,
            'distancia_max_metros' => 150,
        ]);

        $response->assertRedirect(route('miembros-equipo.index'));
        $this->assertDatabaseHas('miembros_equipo', [
            'user_id' => $empleado->id,
            'tenant_id' => $tenant->id,
            'distancia_max_metros' => 150,
        ]);
    }

    public function test_usuario_no_admin_recibe_403(): void
    {
        $tenant = Tenant::factory()->create();
        $usuario = User::factory()->create(['tenant_id' => $tenant->id, 'rol' => UserRole::Usuario, 'password' => bcrypt('secret123')]);
        $this->loginAs($usuario);

        $response = $this->get('/miembros-equipo');

        $response->assertForbidden();
    }

    public function test_user_id_es_unico_no_puede_haber_dos_miembros_para_el_mismo_user(): void
    {
        $tenant = Tenant::factory()->create();
        $admin = $this->crearAdmin($tenant);
        $empleado = User::factory()->create(['tenant_id' => $tenant->id]);
        MiembroEquipo::factory()->for($tenant)->create(['user_id' => $empleado->id]);
        $this->loginAs($admin);

        $response = $this->post('/miembros-equipo', [
            'user_id' => $empleado->id,
            'trabajo_latitud' => 40.4168,
            'trabajo_longitud' => -3.7038,
            'distancia_max_metros' => 100,
        ]);

        $response->assertSessionHasErrors('user_id');
        $this->assertSame(1, MiembroEquipo::where('user_id', $empleado->id)->count());
    }

    public function test_al_guardar_con_coords_de_casa_y_trabajo_se_calcula_distancia_casa_trabajo(): void
    {
        $tenant = Tenant::factory()->create();
        $admin = $this->crearAdmin($tenant);
        $empleado = User::factory()->create(['tenant_id' => $tenant->id]);
        $this->loginAs($admin);

        $this->post('/miembros-equipo', [
            'user_id' => $empleado->id,
            'trabajo_latitud' => 40.4168,
            'trabajo_longitud' => -3.7038,
            'distancia_max_metros' => 100,
            'casa_latitud' => 40.42,
            'casa_longitud' => -3.7038,
        ]);

        $miembro = MiembroEquipo::where('user_id', $empleado->id)->first();
        $this->assertNotNull($miembro->distancia_casa_trabajo_metros);
        $this->assertGreaterThan(0, $miembro->distancia_casa_trabajo_metros);
    }

    public function test_aislamiento_entre_tenants_no_se_puede_editar_miembro_de_otro_tenant(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $adminA = $this->crearAdmin($tenantA);
        $empleadoA = User::factory()->create(['tenant_id' => $tenantA->id]);
        $empleadoB = User::factory()->create(['tenant_id' => $tenantB->id]);
        $miembroB = MiembroEquipo::factory()->for($tenantB)->create(['user_id' => $empleadoB->id]);
        $this->loginAs($adminA);

        // user_id válido del propio tenant A: así la validación pasa y lo que se está probando
        // de verdad es que {miembro} (de tenant B) no se resuelve para el admin de tenant A.
        $response = $this->put('/miembros-equipo/'.$miembroB->id, [
            'user_id' => $empleadoA->id,
            'trabajo_latitud' => 40.4168,
            'trabajo_longitud' => -3.7038,
            'distancia_max_metros' => 100,
        ]);

        $response->assertNotFound();
    }

    public function test_baja_marca_dado_baja_y_softdelete_conservando_fichajes(): void
    {
        $tenant = Tenant::factory()->create();
        $admin = $this->crearAdmin($tenant);
        $empleado = User::factory()->create(['tenant_id' => $tenant->id]);
        $miembro = MiembroEquipo::factory()->for($tenant)->create(['user_id' => $empleado->id]);
        Fichaje::factory()->for($tenant)->create(['miembro_equipo_id' => $miembro->id]);
        $this->loginAs($admin);

        $response = $this->delete('/miembros-equipo/'.$miembro->id);

        $response->assertRedirect(route('miembros-equipo.index'));
        $miembro->refresh();
        $this->assertFalse($miembro->activo);
        $this->assertNotNull($miembro->dado_baja_at);
        $this->assertSoftDeleted($miembro);
        $this->assertDatabaseCount('fichajes', 1);
    }
}
