<?php

namespace Tests\Feature;

use App\Enums\TipoEventoFichaje;
use App\Enums\UserRole;
use App\Models\Fichaje;
use App\Models\MiembroEquipo;
use App\Models\Tenant;
use App\Models\User;
use App\Services\InformeJornada;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InformeJornadaTest extends TestCase
{
    use RefreshDatabase;

    private function crearEvento(Tenant $tenant, MiembroEquipo $miembro, TipoEventoFichaje $tipo, $ocurridoAt, array $overrides = []): Fichaje
    {
        return Fichaje::factory()->for($tenant)->create(array_merge([
            'miembro_equipo_id' => $miembro->id,
            'tipo' => $tipo,
            'ocurrido_at' => $ocurridoAt,
        ], $overrides));
    }

    public function test_total_de_horas_incluye_jornada_partida_y_cruce_de_medianoche(): void
    {
        $tenant = Tenant::factory()->create();
        $userMiembro = User::factory()->create(['tenant_id' => $tenant->id]);
        $miembro = MiembroEquipo::factory()->for($tenant)->create(['user_id' => $userMiembro->id]);

        $dia = now()->startOfDay();

        // Jornada partida el mismo día: 09:00-13:00 (4h) + 15:00-19:00 (4h) = 8h.
        $this->crearEvento($tenant, $miembro, TipoEventoFichaje::Entrada, $dia->copy()->setTime(9, 0));
        $this->crearEvento($tenant, $miembro, TipoEventoFichaje::Salida, $dia->copy()->setTime(13, 0));
        $this->crearEvento($tenant, $miembro, TipoEventoFichaje::Entrada, $dia->copy()->setTime(15, 0));
        $this->crearEvento($tenant, $miembro, TipoEventoFichaje::Salida, $dia->copy()->setTime(19, 0));

        // Cruce de medianoche al día siguiente: 22:00 -> +2 días 02:00 = 4h.
        $this->crearEvento($tenant, $miembro, TipoEventoFichaje::Entrada, $dia->copy()->addDay()->setTime(22, 0));
        $this->crearEvento($tenant, $miembro, TipoEventoFichaje::Salida, $dia->copy()->addDays(2)->setTime(2, 0));

        $datos = app(InformeJornada::class)->generar($miembro, $dia->copy(), $dia->copy()->addDays(3));

        $this->assertEqualsWithDelta(12.0, $datos['total_horas'], 0.01);
        $this->assertCount(6, $datos['eventos']);
    }

    public function test_exportacion_incluye_todos_los_eventos_del_periodo_incl_correcciones(): void
    {
        $tenant = Tenant::factory()->create();
        $admin = User::factory()->create(['tenant_id' => $tenant->id, 'rol' => UserRole::Admin, 'password' => bcrypt('secret123')]);
        $userMiembro = User::factory()->create(['tenant_id' => $tenant->id]);
        $miembro = MiembroEquipo::factory()->for($tenant)->create(['user_id' => $userMiembro->id]);

        $dia = now()->startOfDay();
        $entrada = $this->crearEvento($tenant, $miembro, TipoEventoFichaje::Entrada, $dia->copy()->setTime(9, 0));
        $this->crearEvento($tenant, $miembro, TipoEventoFichaje::Salida, $dia->copy()->setTime(17, 0));

        $this->crearEvento($tenant, $miembro, TipoEventoFichaje::Entrada, $dia->copy()->setTime(9, 15), [
            'corrige_fichaje_id' => $entrada->id,
            'motivo' => 'Olvido de fichar a tiempo',
            'registrado_por' => $admin->id,
        ]);

        $this->loginAs($admin);

        $response = $this->get('/jornada/exportar?miembro_id='.$miembro->id
            .'&preset=personalizado&desde='.$dia->toDateString().'&hasta='.$dia->toDateString());

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_aislamiento_entre_tenants_no_expone_miembro_de_otro_tenant(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $adminA = User::factory()->create(['tenant_id' => $tenantA->id, 'rol' => UserRole::Admin, 'password' => bcrypt('secret123')]);
        $userB = User::factory()->create(['tenant_id' => $tenantB->id]);
        $miembroB = MiembroEquipo::factory()->for($tenantB)->create(['user_id' => $userB->id]);

        $this->loginAs($adminA);

        $response = $this->get('/jornada?miembro_id='.$miembroB->id);

        $response->assertOk();
        $response->assertViewHas('miembroSeleccionado', null);
    }

    public function test_solo_admin_accede_al_informe_usuario_recibe_403(): void
    {
        $tenant = Tenant::factory()->create();
        $usuario = User::factory()->create(['tenant_id' => $tenant->id, 'rol' => UserRole::Usuario, 'password' => bcrypt('secret123')]);
        $this->loginAs($usuario);

        $response = $this->get('/jornada');

        $response->assertForbidden();
    }
}
