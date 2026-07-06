<?php

namespace Tests\Feature;

use App\Models\AsignacionHorario;
use App\Models\Horario;
use App\Models\HorarioTramo;
use App\Models\MiembroEquipo;
use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MiJornadaTurnoEsperadoTest extends TestCase
{
    use RefreshDatabase;

    public function test_muestra_el_turno_previsto_de_hoy_segun_el_horario_vigente(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-05')); // miércoles (ISO dia_semana=3)

        $tenant = Tenant::factory()->create();
        $empleado = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $miembro = MiembroEquipo::factory()->for($tenant)->create(['user_id' => $empleado->id]);
        $horario = Horario::factory()->for($tenant)->create();
        HorarioTramo::factory()->for($tenant)->create([
            'horario_id' => $horario->id,
            'dia_semana' => 3,
            'hora_inicio' => '09:00:00',
            'hora_fin' => '17:00:00',
        ]);
        AsignacionHorario::factory()->for($tenant)->create([
            'miembro_equipo_id' => $miembro->id,
            'horario_id' => $horario->id,
            'vigente_desde' => '2026-01-01',
            'vigente_hasta' => null,
        ]);
        $this->loginAs($empleado);

        $response = $this->get('/mi-jornada');

        $response->assertOk();
        $response->assertSee('09:00');
        $response->assertSee('17:00');

        Carbon::setTestNow();
    }

    public function test_dia_libre_sin_tramos_no_rompe_la_vista(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-08')); // sábado (ISO dia_semana=6), sin tramos

        $tenant = Tenant::factory()->create();
        $empleado = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $miembro = MiembroEquipo::factory()->for($tenant)->create(['user_id' => $empleado->id]);
        $horario = Horario::factory()->for($tenant)->create();
        HorarioTramo::factory()->for($tenant)->create([
            'horario_id' => $horario->id,
            'dia_semana' => 1,
            'hora_inicio' => '09:00:00',
            'hora_fin' => '17:00:00',
        ]);
        AsignacionHorario::factory()->for($tenant)->create([
            'miembro_equipo_id' => $miembro->id,
            'horario_id' => $horario->id,
            'vigente_desde' => '2026-01-01',
            'vigente_hasta' => null,
        ]);
        $this->loginAs($empleado);

        $response = $this->get('/mi-jornada');

        $response->assertOk();
        $response->assertSee('Día libre');

        Carbon::setTestNow();
    }

    public function test_estado_vacio_sin_horario_asignado(): void
    {
        $tenant = Tenant::factory()->create();
        $empleado = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        MiembroEquipo::factory()->for($tenant)->create(['user_id' => $empleado->id]);
        $this->loginAs($empleado);

        $response = $this->get('/mi-jornada');

        $response->assertOk();
        $response->assertSee('Sin horario asignado');
    }
}
