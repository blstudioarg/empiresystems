<?php

namespace Tests\Feature;

use App\Enums\EstadoAlerta;
use App\Enums\UserRole;
use App\Models\Alerta;
use App\Models\Configuracion;
use App\Models\Fichaje;
use App\Models\MiembroEquipo;
use App\Models\Tenant;
use App\Models\User;
use App\Support\ConfigFichajes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AlertaFichajeTest extends TestCase
{
    use RefreshDatabase;

    private function crearMiembroConTrabajo(Tenant $tenant, User $user, int $distanciaMax = 100): MiembroEquipo
    {
        return MiembroEquipo::factory()->for($tenant)->create([
            'user_id' => $user->id,
            'trabajo_latitud' => 40.4168,
            'trabajo_longitud' => -3.7038,
            'distancia_max_metros' => $distanciaMax,
        ]);
    }

    public function test_fichaje_fuera_crea_exactamente_una_alerta_enlazada(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $this->crearMiembroConTrabajo($tenant, $user, 100);
        $this->loginAs($user);

        $this->post('/fichajes', ['tipo' => 'entrada', 'latitud' => 40.4268, 'longitud' => -3.7038]);

        $fichaje = Fichaje::first();
        $this->assertSame('fuera', $fichaje->resultado_ubicacion->value);

        $this->assertDatabaseCount('alertas', 1);
        $alerta = Alerta::first();
        $this->assertSame($fichaje->id, $alerta->fichaje_id);
        $this->assertSame('fichaje_fuera_de_rango', $alerta->tipo->value);
        $this->assertSame('nueva', $alerta->estado->value);
        $this->assertSame($fichaje->distancia_metros, $alerta->distancia_metros);
    }

    public function test_fichaje_dentro_no_crea_alerta(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $this->crearMiembroConTrabajo($tenant, $user, 100);
        $this->loginAs($user);

        $this->post('/fichajes', ['tipo' => 'entrada', 'latitud' => 40.4168, 'longitud' => -3.7038]);

        $this->assertDatabaseCount('alertas', 0);
    }

    public function test_fichaje_sin_ubicacion_no_crea_alerta(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        MiembroEquipo::factory()->for($tenant)->create(['user_id' => $user->id]);
        $this->loginAs($user);

        $this->post('/fichajes', ['tipo' => 'entrada']);

        $this->assertDatabaseCount('alertas', 0);
    }

    public function test_geofencing_bloqueante_no_crea_fichaje_ni_alerta(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $this->crearMiembroConTrabajo($tenant, $user, 100);
        Configuracion::factory()->for($tenant)->create([
            'clave' => ConfigFichajes::CLAVE_GEOFENCING_BLOQUEANTE,
            'valor' => '1',
        ]);
        $this->loginAs($user);

        $this->post('/fichajes', ['tipo' => 'entrada', 'latitud' => 40.4268, 'longitud' => -3.7038]);

        $this->assertDatabaseCount('fichajes', 0);
        $this->assertDatabaseCount('alertas', 0);
    }

    public function test_admin_cambia_estado_a_resuelta_sin_borrarla(): void
    {
        $tenant = Tenant::factory()->create();
        $admin = User::factory()->create(['tenant_id' => $tenant->id, 'rol' => UserRole::Admin, 'password' => bcrypt('secret123')]);
        $userMiembro = User::factory()->create(['tenant_id' => $tenant->id]);
        $miembro = $this->crearMiembroConTrabajo($tenant, $userMiembro, 100);
        $fichaje = Fichaje::factory()->for($tenant)->fuera()->create(['miembro_equipo_id' => $miembro->id]);
        $alerta = Alerta::factory()->for($tenant)->create(['miembro_equipo_id' => $miembro->id, 'fichaje_id' => $fichaje->id]);

        $this->loginAs($admin);

        $response = $this->patch("/alertas/{$alerta->id}", ['estado' => 'resuelta']);

        $response->assertRedirect();
        $alerta->refresh();
        $this->assertSame('resuelta', $alerta->estado->value);
        $this->assertSame($admin->id, $alerta->resuelta_por);
        $this->assertNotNull($alerta->resuelta_at);
        $this->assertDatabaseCount('alertas', 1);
    }

    public function test_aislamiento_entre_tenants(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $adminA = User::factory()->create(['tenant_id' => $tenantA->id, 'rol' => UserRole::Admin, 'password' => bcrypt('secret123')]);

        $userB = User::factory()->create(['tenant_id' => $tenantB->id]);
        $miembroB = $this->crearMiembroConTrabajo($tenantB, $userB, 100);
        $fichajeB = Fichaje::factory()->for($tenantB)->fuera()->create(['miembro_equipo_id' => $miembroB->id]);
        Alerta::factory()->for($tenantB)->create(['miembro_equipo_id' => $miembroB->id, 'fichaje_id' => $fichajeB->id]);

        $userA = User::factory()->create(['tenant_id' => $tenantA->id]);
        $miembroA = $this->crearMiembroConTrabajo($tenantA, $userA, 100);
        $fichajeA = Fichaje::factory()->for($tenantA)->fuera()->create(['miembro_equipo_id' => $miembroA->id]);
        Alerta::factory()->for($tenantA)->create(['miembro_equipo_id' => $miembroA->id, 'fichaje_id' => $fichajeA->id]);

        $this->loginAs($adminA);

        $response = $this->getJson('/alertas');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }

    public function test_solo_admin_accede_a_alertas(): void
    {
        $tenant = Tenant::factory()->create();
        $usuario = User::factory()->create(['tenant_id' => $tenant->id, 'rol' => UserRole::Usuario, 'password' => bcrypt('secret123')]);
        $this->loginAs($usuario);

        $response = $this->get('/alertas');

        $response->assertForbidden();
    }
}
