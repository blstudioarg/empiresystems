<?php

namespace Tests\Feature;

use App\Enums\EstadoAlerta;
use App\Enums\TipoAlerta;
use App\Enums\TipoEventoFichaje;
use App\Models\Alerta;
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

class EvaluarCumplimientoJornadaTest extends TestCase
{
    use RefreshDatabase;

    private function crearMiembroConHorarioLunesAViernes(Tenant $tenant): MiembroEquipo
    {
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

        return $miembro;
    }

    public function test_el_comando_crea_alertas_de_ausencia_y_retraso_del_dia_anterior(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-06')); // jueves: "ayer" = miércoles 2026-08-05

        $tenant = Tenant::factory()->create();
        $ausente = $this->crearMiembroConHorarioLunesAViernes($tenant);
        $tarde = $this->crearMiembroConHorarioLunesAViernes($tenant);

        $ayer = Carbon::parse('2026-08-05');
        Fichaje::factory()->for($tenant)->create(['miembro_equipo_id' => $tarde->id, 'tipo' => TipoEventoFichaje::Entrada, 'ocurrido_at' => $ayer->copy()->setTime(9, 30)]);
        Fichaje::factory()->for($tenant)->create(['miembro_equipo_id' => $tarde->id, 'tipo' => TipoEventoFichaje::Salida, 'ocurrido_at' => $ayer->copy()->setTime(17, 0)]);

        $this->artisan('jornada:evaluar-cumplimiento')->assertExitCode(0);

        $this->assertDatabaseHas('alertas', [
            'tenant_id' => $tenant->id,
            'miembro_equipo_id' => $ausente->id,
            'tipo' => TipoAlerta::AusenciaJornada->value,
            'estado' => EstadoAlerta::Nueva->value,
        ]);
        $this->assertDatabaseHas('alertas', [
            'tenant_id' => $tenant->id,
            'miembro_equipo_id' => $tarde->id,
            'tipo' => TipoAlerta::RetrasoJornada->value,
            'estado' => EstadoAlerta::Nueva->value,
        ]);

        Carbon::setTestNow();
    }

    public function test_reejecutar_el_comando_no_duplica_alertas(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-06'));

        $tenant = Tenant::factory()->create();
        $this->crearMiembroConHorarioLunesAViernes($tenant);

        $this->artisan('jornada:evaluar-cumplimiento');
        $this->artisan('jornada:evaluar-cumplimiento');

        $this->assertSame(1, Alerta::where('tipo', TipoAlerta::AusenciaJornada->value)->count());

        Carbon::setTestNow();
    }

    public function test_aislamiento_entre_tenants(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-06'));

        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $miembroA = $this->crearMiembroConHorarioLunesAViernes($tenantA);
        $miembroB = $this->crearMiembroConHorarioLunesAViernes($tenantB);

        $this->artisan('jornada:evaluar-cumplimiento');

        $alertaA = Alerta::where('miembro_equipo_id', $miembroA->id)->firstOrFail();
        $alertaB = Alerta::where('miembro_equipo_id', $miembroB->id)->firstOrFail();

        $this->assertSame($tenantA->id, $alertaA->tenant_id);
        $this->assertSame($tenantB->id, $alertaB->tenant_id);

        Carbon::setTestNow();
    }

    public function test_no_toca_las_alertas_existentes_de_fichaje_fuera_de_rango(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-06'));

        $tenant = Tenant::factory()->create();
        $miembro = $this->crearMiembroConHorarioLunesAViernes($tenant);
        $fichaje = Fichaje::factory()->for($tenant)->fuera()->create(['miembro_equipo_id' => $miembro->id]);
        $alertaFichaje = Alerta::factory()->for($tenant)->create([
            'miembro_equipo_id' => $miembro->id,
            'fichaje_id' => $fichaje->id,
            'tipo' => TipoAlerta::FichajeFueraDeRango,
            'distancia_metros' => 500,
            'estado' => EstadoAlerta::Nueva,
        ]);

        $this->artisan('jornada:evaluar-cumplimiento');

        $this->assertDatabaseHas('alertas', [
            'id' => $alertaFichaje->id,
            'tipo' => TipoAlerta::FichajeFueraDeRango->value,
            'estado' => EstadoAlerta::Nueva->value,
        ]);
        $this->assertSame(1, Alerta::where('tipo', TipoAlerta::FichajeFueraDeRango->value)->count());

        Carbon::setTestNow();
    }
}
