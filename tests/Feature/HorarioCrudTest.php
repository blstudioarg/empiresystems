<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\AsignacionHorario;
use App\Models\Horario;
use App\Models\MiembroEquipo;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HorarioCrudTest extends TestCase
{
    use RefreshDatabase;

    private function crearAdmin(Tenant $tenant): User
    {
        return User::factory()->create(['tenant_id' => $tenant->id, 'rol' => UserRole::Admin, 'password' => bcrypt('secret123')]);
    }

    private function tramosTurnoPartido(): array
    {
        $tramos = [];

        foreach (range(1, 5) as $dia) {
            $tramos[] = ['dia_semana' => $dia, 'hora_inicio' => '09:00', 'hora_fin' => '13:00'];
            $tramos[] = ['dia_semana' => $dia, 'hora_inicio' => '15:00', 'hora_fin' => '19:00'];
        }

        return $tramos;
    }

    public function test_crear_horario_con_turno_partido_calcula_40_horas_semana(): void
    {
        $tenant = Tenant::factory()->create();
        $admin = $this->crearAdmin($tenant);
        $this->loginAs($admin);

        $response = $this->postJson('/horarios', [
            'nombre' => 'Jornada mañana',
            'activo' => true,
            'tramos' => $this->tramosTurnoPartido(),
        ]);

        $response->assertCreated();

        $horario = Horario::where('tenant_id', $tenant->id)->where('nombre', 'Jornada mañana')->firstOrFail();

        $this->assertSame(10, $horario->tramos()->count());
        $this->assertSame(40.0, $horario->horasPrevistasSemana());
        $this->assertSame(0.0, $horario->horasPrevistasDia(6));
        $this->assertSame(0.0, $horario->horasPrevistasDia(7));
    }

    public function test_rechaza_tramo_con_hora_fin_menor_o_igual_a_hora_inicio(): void
    {
        $tenant = Tenant::factory()->create();
        $admin = $this->crearAdmin($tenant);
        $this->loginAs($admin);

        $response = $this->postJson('/horarios', [
            'nombre' => 'Horario inválido',
            'activo' => true,
            'tramos' => [
                ['dia_semana' => 1, 'hora_inicio' => '09:00', 'hora_fin' => '09:00'],
            ],
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseMissing('horarios', ['tenant_id' => $tenant->id, 'nombre' => 'Horario inválido']);
    }

    public function test_rechaza_tramos_solapados_el_mismo_dia(): void
    {
        $tenant = Tenant::factory()->create();
        $admin = $this->crearAdmin($tenant);
        $this->loginAs($admin);

        $response = $this->postJson('/horarios', [
            'nombre' => 'Horario con solape',
            'activo' => true,
            'tramos' => [
                ['dia_semana' => 1, 'hora_inicio' => '09:00', 'hora_fin' => '14:00'],
                ['dia_semana' => 1, 'hora_inicio' => '13:00', 'hora_fin' => '17:00'],
            ],
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseMissing('horarios', ['tenant_id' => $tenant->id, 'nombre' => 'Horario con solape']);
    }

    public function test_nombre_de_horario_unico_por_tenant(): void
    {
        $tenant = Tenant::factory()->create();
        $admin = $this->crearAdmin($tenant);
        Horario::factory()->for($tenant)->create(['nombre' => 'Jornada mañana']);
        $this->loginAs($admin);

        $response = $this->postJson('/horarios', [
            'nombre' => 'Jornada mañana',
            'activo' => true,
            'tramos' => [
                ['dia_semana' => 1, 'hora_inicio' => '09:00', 'hora_fin' => '13:00'],
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('nombre');
    }

    public function test_aislamiento_entre_tenants_no_ve_ni_edita_horario_de_otro_tenant(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $adminA = $this->crearAdmin($tenantA);
        $horarioB = Horario::factory()->for($tenantB)->create();
        $this->loginAs($adminA);

        $response = $this->putJson('/horarios/'.$horarioB->id, [
            'nombre' => 'Renombrado',
            'activo' => true,
            'tramos' => [],
        ]);

        $response->assertNotFound();

        $indice = $this->getJson('/horarios');
        $indice->assertJsonMissing(['nombre' => $horarioB->nombre]);
    }

    public function test_borrar_horario_con_asignaciones_responde_422_y_no_borra(): void
    {
        $tenant = Tenant::factory()->create();
        $admin = $this->crearAdmin($tenant);
        $empleado = User::factory()->create(['tenant_id' => $tenant->id]);
        $miembro = MiembroEquipo::factory()->for($tenant)->create(['user_id' => $empleado->id]);
        $horario = Horario::factory()->for($tenant)->create();
        AsignacionHorario::factory()->for($tenant)->create([
            'miembro_equipo_id' => $miembro->id,
            'horario_id' => $horario->id,
        ]);
        $this->loginAs($admin);

        $response = $this->deleteJson('/horarios/'.$horario->id);

        $response->assertStatus(422);
        $this->assertDatabaseHas('horarios', ['id' => $horario->id]);
        $this->assertNull($horario->fresh()->deleted_at);
    }
}
