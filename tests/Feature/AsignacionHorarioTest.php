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

class AsignacionHorarioTest extends TestCase
{
    use RefreshDatabase;

    private function crearAdmin(Tenant $tenant): User
    {
        return User::factory()->create(['tenant_id' => $tenant->id, 'rol' => UserRole::Admin, 'password' => bcrypt('secret123')]);
    }

    public function test_admin_asigna_horario_a_un_miembro_via_endpoint(): void
    {
        $tenant = Tenant::factory()->create();
        $admin = $this->crearAdmin($tenant);
        $empleado = User::factory()->create(['tenant_id' => $tenant->id]);
        $miembro = MiembroEquipo::factory()->for($tenant)->create(['user_id' => $empleado->id]);
        $horario = Horario::factory()->for($tenant)->create();
        $this->loginAs($admin);

        $response = $this->postJson("/miembros-equipo/{$miembro->id}/horarios", [
            'horario_id' => $horario->id,
            'vigente_desde' => '2026-08-01',
        ]);

        $response->assertCreated();
        $asignacion = AsignacionHorario::where('miembro_equipo_id', $miembro->id)->firstOrFail();
        $this->assertSame($tenant->id, $asignacion->tenant_id);
        $this->assertSame($horario->id, $asignacion->horario_id);
        $this->assertSame('2026-08-01', $asignacion->vigente_desde->toDateString());
        $this->assertNull($asignacion->vigente_hasta);
    }

    public function test_historico_de_asignaciones_ordenado_desc(): void
    {
        $tenant = Tenant::factory()->create();
        $admin = $this->crearAdmin($tenant);
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
        AsignacionHorario::factory()->for($tenant)->create([
            'miembro_equipo_id' => $miembro->id,
            'horario_id' => $horarioB->id,
            'vigente_desde' => '2026-03-01',
            'vigente_hasta' => null,
        ]);
        $this->loginAs($admin);

        $response = $this->getJson("/miembros-equipo/{$miembro->id}/horarios");

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(2, $data);
        $this->assertSame('2026-03-01', $data[0]['vigente_desde']);
        $this->assertTrue($data[0]['es_vigente']);
        $this->assertSame('2026-01-01', $data[1]['vigente_desde']);
        $this->assertFalse($data[1]['es_vigente']);
    }

    public function test_aislamiento_entre_tenants_en_asignaciones(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $adminA = $this->crearAdmin($tenantA);
        $empleadoB = User::factory()->create(['tenant_id' => $tenantB->id]);
        $miembroB = MiembroEquipo::factory()->for($tenantB)->create(['user_id' => $empleadoB->id]);
        // Horario del propio tenant A: así la validación de `horario_id` pasa y lo que se
        // prueba de verdad es que {miembro} (de tenant B) no se resuelve para el admin de A.
        $horarioA = Horario::factory()->for($tenantA)->create();
        $this->loginAs($adminA);

        $response = $this->postJson("/miembros-equipo/{$miembroB->id}/horarios", [
            'horario_id' => $horarioA->id,
            'vigente_desde' => '2026-08-01',
        ]);

        $response->assertNotFound();
    }

    public function test_historico_se_conserva_tras_baja_del_miembro(): void
    {
        $tenant = Tenant::factory()->create();
        $admin = $this->crearAdmin($tenant);
        $empleado = User::factory()->create(['tenant_id' => $tenant->id]);
        $miembro = MiembroEquipo::factory()->for($tenant)->create(['user_id' => $empleado->id]);
        $horario = Horario::factory()->for($tenant)->create();
        AsignacionHorario::factory()->for($tenant)->create([
            'miembro_equipo_id' => $miembro->id,
            'horario_id' => $horario->id,
            'vigente_desde' => '2026-01-01',
            'vigente_hasta' => null,
        ]);
        $this->loginAs($admin);

        $this->delete('/miembros-equipo/'.$miembro->id);

        $this->assertDatabaseHas('asignaciones_horario', [
            'miembro_equipo_id' => $miembro->id,
            'horario_id' => $horario->id,
        ]);
    }
}
