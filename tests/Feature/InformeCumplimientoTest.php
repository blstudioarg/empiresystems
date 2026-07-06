<?php

namespace Tests\Feature;

use App\Enums\TipoEventoFichaje;
use App\Enums\UserRole;
use App\Enums\VeredictoCumplimiento;
use App\Models\AsignacionHorario;
use App\Models\Fichaje;
use App\Models\Horario;
use App\Models\HorarioTramo;
use App\Models\MiembroEquipo;
use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InformeCumplimientoTest extends TestCase
{
    use RefreshDatabase;

    private function crearAdmin(Tenant $tenant): User
    {
        return User::factory()->create(['tenant_id' => $tenant->id, 'rol' => UserRole::Admin, 'password' => bcrypt('secret123')]);
    }

    public function test_informe_muestra_cumplimiento_por_dia_del_rango(): void
    {
        $tenant = Tenant::factory()->create();
        $admin = $this->crearAdmin($tenant);
        $empleado = User::factory()->create(['tenant_id' => $tenant->id]);
        $miembro = MiembroEquipo::factory()->for($tenant)->create(['user_id' => $empleado->id]);
        $horario = Horario::factory()->for($tenant)->create();
        foreach (range(1, 5) as $dia) {
            HorarioTramo::factory()->for($tenant)->create([
                'horario_id' => $horario->id,
                'dia_semana' => $dia,
                'hora_inicio' => '09:00:00',
                'hora_fin' => '17:00:00',
            ]);
        }
        AsignacionHorario::factory()->for($tenant)->create([
            'miembro_equipo_id' => $miembro->id,
            'horario_id' => $horario->id,
            'vigente_desde' => '2026-01-01',
            'vigente_hasta' => null,
        ]);

        // Lunes 2026-08-03: retraso. Martes 2026-08-04: ausencia (sin fichar).
        $lunes = Carbon::parse('2026-08-03');
        Fichaje::factory()->for($tenant)->create(['miembro_equipo_id' => $miembro->id, 'tipo' => TipoEventoFichaje::Entrada, 'ocurrido_at' => $lunes->copy()->setTime(9, 20)]);
        Fichaje::factory()->for($tenant)->create(['miembro_equipo_id' => $miembro->id, 'tipo' => TipoEventoFichaje::Salida, 'ocurrido_at' => $lunes->copy()->setTime(17, 0)]);

        $this->loginAs($admin);

        $response = $this->get('/jornada?preset=personalizado&miembro_id='.$miembro->id.'&desde=2026-08-03&hasta=2026-08-04');

        $response->assertOk();
        $response->assertViewHas('cumplimiento', function ($cumplimiento) {
            return $cumplimiento->count() === 2
                && $cumplimiento[0]->veredicto === VeredictoCumplimiento::Retraso
                && $cumplimiento[1]->veredicto === VeredictoCumplimiento::Ausencia;
        });
    }

    public function test_cada_dia_se_compara_contra_el_horario_vigente_de_ese_dia(): void
    {
        $tenant = Tenant::factory()->create();
        $admin = $this->crearAdmin($tenant);
        $empleado = User::factory()->create(['tenant_id' => $tenant->id]);
        $miembro = MiembroEquipo::factory()->for($tenant)->create(['user_id' => $empleado->id]);

        $horarioA = Horario::factory()->for($tenant)->create();
        HorarioTramo::factory()->for($tenant)->create(['horario_id' => $horarioA->id, 'dia_semana' => 3, 'hora_inicio' => '09:00:00', 'hora_fin' => '13:00:00']);
        $horarioB = Horario::factory()->for($tenant)->create();
        HorarioTramo::factory()->for($tenant)->create(['horario_id' => $horarioB->id, 'dia_semana' => 3, 'hora_inicio' => '09:00:00', 'hora_fin' => '17:00:00']);

        AsignacionHorario::factory()->for($tenant)->create([
            'miembro_equipo_id' => $miembro->id, 'horario_id' => $horarioA->id,
            'vigente_desde' => '2026-01-01', 'vigente_hasta' => '2026-07-31',
        ]);
        AsignacionHorario::factory()->for($tenant)->create([
            'miembro_equipo_id' => $miembro->id, 'horario_id' => $horarioB->id,
            'vigente_desde' => '2026-08-01', 'vigente_hasta' => null,
        ]);

        $this->loginAs($admin);

        // 2026-07-29 (miércoles) bajo horarioA (4h previstas); 2026-08-05 (miércoles) bajo horarioB (8h previstas).
        $response = $this->get('/jornada?preset=personalizado&miembro_id='.$miembro->id.'&desde=2026-07-29&hasta=2026-08-05');

        $response->assertOk();
        $response->assertViewHas('cumplimiento', function ($cumplimiento) {
            $primero = $cumplimiento->first();
            $ultimo = $cumplimiento->last();

            return $primero->horasPrevistas === 4.0 && $ultimo->horasPrevistas === 8.0;
        });
    }

    public function test_aislamiento_entre_tenants_en_el_informe_de_cumplimiento(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $adminA = $this->crearAdmin($tenantA);
        $empleadoB = User::factory()->create(['tenant_id' => $tenantB->id]);
        $miembroB = MiembroEquipo::factory()->for($tenantB)->create(['user_id' => $empleadoB->id]);
        $this->loginAs($adminA);

        $response = $this->get('/jornada?preset=personalizado&miembro_id='.$miembroB->id.'&desde=2026-08-01&hasta=2026-08-05');

        $response->assertOk();
        $response->assertViewHas('miembroSeleccionado', null);
    }

    public function test_estado_vacio_sin_miembro_seleccionado_no_rompe(): void
    {
        $tenant = Tenant::factory()->create();
        $admin = $this->crearAdmin($tenant);
        $this->loginAs($admin);

        $response = $this->get('/jornada');

        $response->assertOk();
        $response->assertViewHas('cumplimiento', null);
    }
}
