<?php

namespace Tests\Unit;

use App\Models\AsignacionHorario;
use App\Models\Horario;
use App\Models\MiembroEquipo;
use App\Models\Tenant;
use App\Models\User;
use App\Support\ResolutorHorario;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AsignacionHorarioVigenteTest extends TestCase
{
    use RefreshDatabase;

    public function test_resuelve_el_horario_vigente_segun_la_fecha_consultada(): void
    {
        $tenant = Tenant::factory()->create();
        $empleado = User::factory()->create(['tenant_id' => $tenant->id]);
        $miembro = MiembroEquipo::factory()->for($tenant)->create(['user_id' => $empleado->id]);
        $horarioA = Horario::factory()->for($tenant)->create(['nombre' => 'Horario A']);
        $horarioB = Horario::factory()->for($tenant)->create(['nombre' => 'Horario B']);

        AsignacionHorario::factory()->for($tenant)->create([
            'miembro_equipo_id' => $miembro->id,
            'horario_id' => $horarioA->id,
            'vigente_desde' => '2026-01-01',
            'vigente_hasta' => '2026-02-28',
        ]);
        AsignacionHorario::factory()->for($tenant)->create([
            'miembro_equipo_id' => $miembro->id,
            'horario_id' => $horarioB->id,
            'vigente_desde' => '2026-03-01',
            'vigente_hasta' => null,
        ]);

        $this->assertSame($horarioA->id, ResolutorHorario::vigente($miembro, Carbon::parse('2026-02-15'))?->id);
        $this->assertSame($horarioB->id, ResolutorHorario::vigente($miembro, Carbon::parse('2026-03-15'))?->id);
        $this->assertNull(ResolutorHorario::vigente($miembro, Carbon::parse('2025-12-31')));
    }

    public function test_asignar_horario_nuevo_cierra_automaticamente_el_anterior(): void
    {
        $tenant = Tenant::factory()->create();
        $empleado = User::factory()->create(['tenant_id' => $tenant->id]);
        $miembro = MiembroEquipo::factory()->for($tenant)->create(['user_id' => $empleado->id]);
        $horarioA = Horario::factory()->for($tenant)->create();
        $horarioB = Horario::factory()->for($tenant)->create();

        $anterior = AsignacionHorario::factory()->for($tenant)->create([
            'miembro_equipo_id' => $miembro->id,
            'horario_id' => $horarioA->id,
            'vigente_desde' => '2026-01-01',
            'vigente_hasta' => null,
        ]);

        app(\App\Support\AsignadorHorario::class)->asignar($miembro, $horarioB, Carbon::parse('2026-03-01'));

        $anterior->refresh();
        $this->assertSame('2026-02-28', $anterior->vigente_hasta->toDateString());
        $this->assertSame($horarioB->id, ResolutorHorario::vigente($miembro, Carbon::parse('2026-03-01'))?->id);
    }

    public function test_rechaza_asignacion_que_solapa_un_rango_cerrado_existente(): void
    {
        $tenant = Tenant::factory()->create();
        $empleado = User::factory()->create(['tenant_id' => $tenant->id]);
        $miembro = MiembroEquipo::factory()->for($tenant)->create(['user_id' => $empleado->id]);
        $horarioA = Horario::factory()->for($tenant)->create();
        $horarioB = Horario::factory()->for($tenant)->create();

        AsignacionHorario::factory()->for($tenant)->create([
            'miembro_equipo_id' => $miembro->id,
            'horario_id' => $horarioA->id,
            'vigente_desde' => '2026-01-01',
            'vigente_hasta' => '2026-02-28',
        ]);

        $this->expectException(\App\Exceptions\AsignacionHorarioSolapadaException::class);

        app(\App\Support\AsignadorHorario::class)->asignar($miembro, $horarioB, Carbon::parse('2026-02-15'));
    }
}
