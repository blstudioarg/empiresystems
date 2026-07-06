<?php

namespace Tests\Feature;

use App\Enums\TipoEventoFichaje;
use App\Models\Fichaje;
use App\Models\MiembroEquipo;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PortalMiJornadaTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_miembro_ve_solo_sus_propios_fichajes_y_su_total_de_horas(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $miembro = MiembroEquipo::factory()->for($tenant)->create(['user_id' => $user->id]);

        $dia = now()->startOfDay();
        Fichaje::factory()->for($tenant)->create(['miembro_equipo_id' => $miembro->id, 'tipo' => TipoEventoFichaje::Entrada, 'ocurrido_at' => $dia->copy()->setTime(9, 0)]);
        Fichaje::factory()->for($tenant)->create(['miembro_equipo_id' => $miembro->id, 'tipo' => TipoEventoFichaje::Salida, 'ocurrido_at' => $dia->copy()->setTime(17, 0)]);

        // Fichajes de otro miembro del MISMO tenant: no deben aparecer en el portal propio.
        $otroUser = User::factory()->create(['tenant_id' => $tenant->id]);
        $otroMiembro = MiembroEquipo::factory()->for($tenant)->create(['user_id' => $otroUser->id]);
        Fichaje::factory()->for($tenant)->create(['miembro_equipo_id' => $otroMiembro->id, 'tipo' => TipoEventoFichaje::Entrada, 'ocurrido_at' => $dia->copy()->setTime(8, 0)]);

        $this->loginAs($user);

        $response = $this->get('/mi-jornada?preset=personalizado&desde='.$dia->toDateString().'&hasta='.$dia->toDateString());

        $response->assertOk();
        $response->assertViewHas('datos', function ($datos) use ($miembro) {
            return $datos['eventos']->count() === 2
                && $datos['eventos']->every(fn ($e) => $e->miembro_equipo_id === $miembro->id)
                && abs($datos['total_horas'] - 8.0) < 0.01;
        });
    }

    public function test_intentar_ver_los_de_otro_miembro_via_parametro_es_ignorado(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        MiembroEquipo::factory()->for($tenant)->create(['user_id' => $user->id]);

        $otroUser = User::factory()->create(['tenant_id' => $tenant->id]);
        $otroMiembro = MiembroEquipo::factory()->for($tenant)->create(['user_id' => $otroUser->id]);
        Fichaje::factory()->for($tenant)->create(['miembro_equipo_id' => $otroMiembro->id]);

        $this->loginAs($user);

        // Intenta inyectar el id de otro miembro: el controlador nunca lee este parámetro.
        $response = $this->get('/mi-jornada?miembro_id='.$otroMiembro->id);

        $response->assertOk();
        $response->assertViewHas('datos', fn ($datos) => $datos['eventos']->count() === 0);
    }

    public function test_usuario_sin_perfil_de_miembro_recibe_403(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $this->loginAs($user);

        $response = $this->get('/mi-jornada');

        $response->assertForbidden();
    }

    public function test_aislamiento_entre_tenants(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $userA = User::factory()->create(['tenant_id' => $tenantA->id, 'password' => bcrypt('secret123')]);
        $miembroA = MiembroEquipo::factory()->for($tenantA)->create(['user_id' => $userA->id]);

        $userB = User::factory()->create(['tenant_id' => $tenantB->id]);
        $miembroB = MiembroEquipo::factory()->for($tenantB)->create(['user_id' => $userB->id]);
        Fichaje::factory()->for($tenantB)->create(['miembro_equipo_id' => $miembroB->id]);
        Fichaje::factory()->for($tenantA)->create(['miembro_equipo_id' => $miembroA->id]);

        $this->loginAs($userA);

        $response = $this->get('/mi-jornada');

        $response->assertOk();
        $response->assertViewHas('datos', fn ($datos) => $datos['eventos']->count() === 1);
    }
}
