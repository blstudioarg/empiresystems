<?php

namespace Tests\Unit;

use App\Enums\TipoEventoFichaje;
use App\Enums\VeredictoCumplimiento;
use App\Models\AsignacionHorario;
use App\Models\Fichaje;
use App\Models\Horario;
use App\Models\HorarioTramo;
use App\Models\MiembroEquipo;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Cumplimiento\ServicioCumplimiento;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServicioCumplimientoTest extends TestCase
{
    use RefreshDatabase;

    private function crearMiembroConHorario(Tenant $tenant, int $diaSemana, string $horaInicio, string $horaFin): MiembroEquipo
    {
        $empleado = User::factory()->create(['tenant_id' => $tenant->id]);
        $miembro = MiembroEquipo::factory()->for($tenant)->create(['user_id' => $empleado->id]);
        $horario = Horario::factory()->for($tenant)->create();
        HorarioTramo::factory()->for($tenant)->create([
            'horario_id' => $horario->id,
            'dia_semana' => $diaSemana,
            'hora_inicio' => $horaInicio,
            'hora_fin' => $horaFin,
        ]);
        AsignacionHorario::factory()->for($tenant)->create([
            'miembro_equipo_id' => $miembro->id,
            'horario_id' => $horario->id,
            'vigente_desde' => '2026-01-01',
            'vigente_hasta' => null,
        ]);

        return $miembro;
    }

    public function test_horas_trabajadas_empareja_entrada_salida_y_resta_pausas(): void
    {
        $tenant = Tenant::factory()->create();
        $dia = Carbon::parse('2026-08-05'); // miércoles, dia_semana ISO = 3
        // Objetivo de este test: el emparejamiento entrada/salida y la resta de la pausa
        // fichada (no la clasificación de cumplimiento, que cubren los tests de más abajo).
        $miembro = $this->crearMiembroConHorario($tenant, 3, '09:00:00', '17:00:00');

        Fichaje::factory()->for($tenant)->create(['miembro_equipo_id' => $miembro->id, 'tipo' => TipoEventoFichaje::Entrada, 'ocurrido_at' => $dia->copy()->setTime(9, 0)]);
        Fichaje::factory()->for($tenant)->create(['miembro_equipo_id' => $miembro->id, 'tipo' => TipoEventoFichaje::InicioPausa, 'ocurrido_at' => $dia->copy()->setTime(13, 0)]);
        Fichaje::factory()->for($tenant)->create(['miembro_equipo_id' => $miembro->id, 'tipo' => TipoEventoFichaje::FinPausa, 'ocurrido_at' => $dia->copy()->setTime(14, 0)]);
        Fichaje::factory()->for($tenant)->create(['miembro_equipo_id' => $miembro->id, 'tipo' => TipoEventoFichaje::Salida, 'ocurrido_at' => $dia->copy()->setTime(17, 0)]);

        $resultado = app(ServicioCumplimiento::class)->evaluarDia($miembro, $dia);

        // 8h de tramo (09-17) menos 1h de pausa fichada = 7h trabajadas.
        $this->assertSame(7.0, $resultado->horasTrabajadas);
        $this->assertFalse($resultado->incidencia);
    }

    public function test_fichaje_incompleto_marca_incidencia_y_no_computa_jornada_completa(): void
    {
        $tenant = Tenant::factory()->create();
        $dia = Carbon::parse('2026-08-05');
        $miembro = $this->crearMiembroConHorario($tenant, 3, '09:00:00', '17:00:00');

        Fichaje::factory()->for($tenant)->create(['miembro_equipo_id' => $miembro->id, 'tipo' => TipoEventoFichaje::Entrada, 'ocurrido_at' => $dia->copy()->setTime(9, 0)]);

        $resultado = app(ServicioCumplimiento::class)->evaluarDia($miembro, $dia);

        $this->assertTrue($resultado->incidencia);
        $this->assertSame(0.0, $resultado->horasTrabajadas);
    }

    public function test_dia_libre_no_cuenta_como_ausencia_aunque_no_haya_fichajes(): void
    {
        $tenant = Tenant::factory()->create();
        $dia = Carbon::parse('2026-08-08'); // sábado, sin tramos definidos
        $miembro = $this->crearMiembroConHorario($tenant, 1, '09:00:00', '17:00:00');

        $resultado = app(ServicioCumplimiento::class)->evaluarDia($miembro, $dia);

        $this->assertSame(0.0, $resultado->horasPrevistas);
        $this->assertSame(VeredictoCumplimiento::Libre, $resultado->veredicto);
    }

    public function test_ausencia_cuando_hay_horas_previstas_y_cero_fichajes(): void
    {
        $tenant = Tenant::factory()->create();
        $dia = Carbon::parse('2026-08-05');
        $miembro = $this->crearMiembroConHorario($tenant, 3, '09:00:00', '17:00:00');

        $resultado = app(ServicioCumplimiento::class)->evaluarDia($miembro, $dia);

        $this->assertSame(VeredictoCumplimiento::Ausencia, $resultado->veredicto);
        $this->assertSame(0.0, $resultado->horasTrabajadas);
    }

    public function test_retraso_cuando_la_entrada_llega_mas_tarde_que_el_tramo_mas_tolerancia(): void
    {
        $tenant = Tenant::factory()->create();
        $dia = Carbon::parse('2026-08-05');
        $miembro = $this->crearMiembroConHorario($tenant, 3, '09:00:00', '17:00:00');

        Fichaje::factory()->for($tenant)->create(['miembro_equipo_id' => $miembro->id, 'tipo' => TipoEventoFichaje::Entrada, 'ocurrido_at' => $dia->copy()->setTime(9, 20)]);
        Fichaje::factory()->for($tenant)->create(['miembro_equipo_id' => $miembro->id, 'tipo' => TipoEventoFichaje::Salida, 'ocurrido_at' => $dia->copy()->setTime(17, 0)]);

        $resultado = app(ServicioCumplimiento::class)->evaluarDia($miembro, $dia);

        $this->assertSame(VeredictoCumplimiento::Retraso, $resultado->veredicto);
        $this->assertGreaterThan(0, $resultado->minutosRetraso);
    }

    public function test_cumplimiento_parcial_cuando_trabaja_menos_horas_de_las_previstas(): void
    {
        $tenant = Tenant::factory()->create();
        $dia = Carbon::parse('2026-08-05');
        $miembro = $this->crearMiembroConHorario($tenant, 3, '09:00:00', '17:00:00');

        Fichaje::factory()->for($tenant)->create(['miembro_equipo_id' => $miembro->id, 'tipo' => TipoEventoFichaje::Entrada, 'ocurrido_at' => $dia->copy()->setTime(9, 0)]);
        Fichaje::factory()->for($tenant)->create(['miembro_equipo_id' => $miembro->id, 'tipo' => TipoEventoFichaje::Salida, 'ocurrido_at' => $dia->copy()->setTime(13, 0)]);

        $resultado = app(ServicioCumplimiento::class)->evaluarDia($miembro, $dia);

        $this->assertSame(VeredictoCumplimiento::Parcial, $resultado->veredicto);
        $this->assertLessThan(0, $resultado->diferenciaHoras);
    }

    public function test_exceso_cuando_trabaja_mas_horas_de_las_previstas(): void
    {
        $tenant = Tenant::factory()->create();
        $dia = Carbon::parse('2026-08-05');
        $miembro = $this->crearMiembroConHorario($tenant, 3, '09:00:00', '17:00:00');

        Fichaje::factory()->for($tenant)->create(['miembro_equipo_id' => $miembro->id, 'tipo' => TipoEventoFichaje::Entrada, 'ocurrido_at' => $dia->copy()->setTime(9, 0)]);
        Fichaje::factory()->for($tenant)->create(['miembro_equipo_id' => $miembro->id, 'tipo' => TipoEventoFichaje::Salida, 'ocurrido_at' => $dia->copy()->setTime(18, 30)]);

        $resultado = app(ServicioCumplimiento::class)->evaluarDia($miembro, $dia);

        $this->assertSame(VeredictoCumplimiento::Exceso, $resultado->veredicto);
        $this->assertGreaterThan(0, $resultado->diferenciaHoras);
    }

    public function test_horas_fichadas_fuera_del_tramo_previsto_no_cuentan_como_dentro_de_horario(): void
    {
        $tenant = Tenant::factory()->create();
        $dia = Carbon::parse('2026-08-05');
        $miembro = $this->crearMiembroConHorario($tenant, 3, '09:00:00', '17:00:00');

        // Fichó 2h enteras fuera del tramo previsto (20:00-22:00), nada dentro.
        Fichaje::factory()->for($tenant)->create(['miembro_equipo_id' => $miembro->id, 'tipo' => TipoEventoFichaje::Entrada, 'ocurrido_at' => $dia->copy()->setTime(20, 0)]);
        Fichaje::factory()->for($tenant)->create(['miembro_equipo_id' => $miembro->id, 'tipo' => TipoEventoFichaje::Salida, 'ocurrido_at' => $dia->copy()->setTime(22, 0)]);

        $resultado = app(ServicioCumplimiento::class)->evaluarDia($miembro, $dia);

        // El total trabajado se sigue contando igual (no lo descarta), solo cambia el desglose.
        $this->assertSame(2.0, $resultado->horasTrabajadas);
        $this->assertSame(0.0, $resultado->horasDentroHorario);
        $this->assertSame(2.0, $resultado->horasFueraHorario);
    }

    public function test_horas_fichadas_a_caballo_del_tramo_se_reparten_entre_dentro_y_fuera(): void
    {
        $tenant = Tenant::factory()->create();
        $dia = Carbon::parse('2026-08-05');
        $miembro = $this->crearMiembroConHorario($tenant, 3, '09:00:00', '17:00:00');

        // Entra 1h antes del tramo (08:00) y se va 1h antes de que termine (16:00):
        // 1h fuera (08-09) + 7h dentro (09-16) = 8h trabajadas.
        Fichaje::factory()->for($tenant)->create(['miembro_equipo_id' => $miembro->id, 'tipo' => TipoEventoFichaje::Entrada, 'ocurrido_at' => $dia->copy()->setTime(8, 0)]);
        Fichaje::factory()->for($tenant)->create(['miembro_equipo_id' => $miembro->id, 'tipo' => TipoEventoFichaje::Salida, 'ocurrido_at' => $dia->copy()->setTime(16, 0)]);

        $resultado = app(ServicioCumplimiento::class)->evaluarDia($miembro, $dia);

        $this->assertSame(8.0, $resultado->horasTrabajadas);
        $this->assertSame(7.0, $resultado->horasDentroHorario);
        $this->assertSame(1.0, $resultado->horasFueraHorario);
    }

    public function test_intervalos_dia_devuelve_segmentos_entrada_salida(): void
    {
        $tenant = Tenant::factory()->create();
        $dia = Carbon::parse('2026-08-05');
        $miembro = $this->crearMiembroConHorario($tenant, 3, '09:00:00', '17:00:00');

        Fichaje::factory()->for($tenant)->create(['miembro_equipo_id' => $miembro->id, 'tipo' => TipoEventoFichaje::Entrada, 'ocurrido_at' => $dia->copy()->setTime(9, 0)]);
        Fichaje::factory()->for($tenant)->create(['miembro_equipo_id' => $miembro->id, 'tipo' => TipoEventoFichaje::Salida, 'ocurrido_at' => $dia->copy()->setTime(17, 0)]);

        $intervalos = app(ServicioCumplimiento::class)->intervalosDia($miembro, $dia);

        $this->assertCount(1, $intervalos);
        $this->assertSame($dia->copy()->setTime(9, 0)->getTimestamp(), $intervalos[0][0]->getTimestamp());
        $this->assertSame($dia->copy()->setTime(17, 0)->getTimestamp(), $intervalos[0][1]->getTimestamp());
    }

    public function test_intervalos_dia_la_pausa_parte_el_intervalo_en_dos(): void
    {
        $tenant = Tenant::factory()->create();
        $dia = Carbon::parse('2026-08-05');
        $miembro = $this->crearMiembroConHorario($tenant, 3, '09:00:00', '17:00:00');

        Fichaje::factory()->for($tenant)->create(['miembro_equipo_id' => $miembro->id, 'tipo' => TipoEventoFichaje::Entrada, 'ocurrido_at' => $dia->copy()->setTime(9, 0)]);
        Fichaje::factory()->for($tenant)->create(['miembro_equipo_id' => $miembro->id, 'tipo' => TipoEventoFichaje::InicioPausa, 'ocurrido_at' => $dia->copy()->setTime(13, 0)]);
        Fichaje::factory()->for($tenant)->create(['miembro_equipo_id' => $miembro->id, 'tipo' => TipoEventoFichaje::FinPausa, 'ocurrido_at' => $dia->copy()->setTime(14, 0)]);
        Fichaje::factory()->for($tenant)->create(['miembro_equipo_id' => $miembro->id, 'tipo' => TipoEventoFichaje::Salida, 'ocurrido_at' => $dia->copy()->setTime(17, 0)]);

        $intervalos = app(ServicioCumplimiento::class)->intervalosDia($miembro, $dia);

        $this->assertCount(2, $intervalos);
        $this->assertSame($dia->copy()->setTime(9, 0)->getTimestamp(), $intervalos[0][0]->getTimestamp());
        $this->assertSame($dia->copy()->setTime(13, 0)->getTimestamp(), $intervalos[0][1]->getTimestamp());
        $this->assertSame($dia->copy()->setTime(14, 0)->getTimestamp(), $intervalos[1][0]->getTimestamp());
        $this->assertSame($dia->copy()->setTime(17, 0)->getTimestamp(), $intervalos[1][1]->getTimestamp());
    }

    public function test_intervalos_dia_entrada_sin_salida_no_genera_intervalo(): void
    {
        $tenant = Tenant::factory()->create();
        $dia = Carbon::parse('2026-08-05');
        $miembro = $this->crearMiembroConHorario($tenant, 3, '09:00:00', '17:00:00');

        Fichaje::factory()->for($tenant)->create(['miembro_equipo_id' => $miembro->id, 'tipo' => TipoEventoFichaje::Entrada, 'ocurrido_at' => $dia->copy()->setTime(9, 0)]);

        $this->assertSame([], app(ServicioCumplimiento::class)->intervalosDia($miembro, $dia));
    }

    public function test_intervalos_dia_aplica_correcciones_sobre_el_evento_original(): void
    {
        $tenant = Tenant::factory()->create();
        $dia = Carbon::parse('2026-08-05');
        $miembro = $this->crearMiembroConHorario($tenant, 3, '09:00:00', '17:00:00');

        $original = Fichaje::factory()->for($tenant)->create(['miembro_equipo_id' => $miembro->id, 'tipo' => TipoEventoFichaje::Entrada, 'ocurrido_at' => $dia->copy()->setTime(9, 30)]);
        Fichaje::factory()->for($tenant)->create(['miembro_equipo_id' => $miembro->id, 'tipo' => TipoEventoFichaje::Salida, 'ocurrido_at' => $dia->copy()->setTime(17, 0)]);
        // Corrección: la entrada real fue a las 09:00, no a las 09:30.
        Fichaje::factory()->for($tenant)->create([
            'miembro_equipo_id' => $miembro->id,
            'tipo' => TipoEventoFichaje::Entrada,
            'ocurrido_at' => $dia->copy()->setTime(9, 0),
            'corrige_fichaje_id' => $original->id,
            'motivo' => 'Olvidó fichar al llegar',
        ]);

        $intervalos = app(ServicioCumplimiento::class)->intervalosDia($miembro, $dia);

        $this->assertCount(1, $intervalos);
        $this->assertSame($dia->copy()->setTime(9, 0)->getTimestamp(), $intervalos[0][0]->getTimestamp());
    }
}
