<?php

namespace Tests\Feature;

use App\Enums\TipoEventoFichaje;
use App\Enums\UserRole;
use App\Models\Fichaje;
use App\Models\MiembroEquipo;
use App\Models\Tenant;
use App\Models\User;
use App\Services\InformeJornada;
use App\Support\ConfigTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CorreccionFichajeTest extends TestCase
{
    use RefreshDatabase;

    public function test_correccion_crea_evento_enlazado_sin_tocar_el_original(): void
    {
        $tenant = Tenant::factory()->create();
        $admin = User::factory()->create(['tenant_id' => $tenant->id, 'rol' => UserRole::Admin, 'password' => bcrypt('secret123')]);
        $userMiembro = User::factory()->create(['tenant_id' => $tenant->id]);
        $miembro = MiembroEquipo::factory()->for($tenant)->create(['user_id' => $userMiembro->id]);

        $original = Fichaje::factory()->for($tenant)->create([
            'miembro_equipo_id' => $miembro->id,
            'tipo' => TipoEventoFichaje::Salida,
            'ocurrido_at' => now()->subHour(),
        ]);

        $this->loginAs($admin);

        $nuevaHora = now()->subMinutes(30);
        $response = $this->post("/fichajes/{$original->id}/corregir", [
            'tipo' => 'salida',
            'ocurrido_at' => $nuevaHora->toDateTimeString(),
            'motivo' => 'Salida olvidada, corregida según el parte manual del vigilante',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseCount('fichajes', 2);

        $original->refresh();
        $this->assertNull($original->corrige_fichaje_id);
        $this->assertNotNull($original->ocurrido_at);

        $correccion = Fichaje::where('corrige_fichaje_id', $original->id)->first();
        $this->assertNotNull($correccion);
        $this->assertSame($admin->id, $correccion->registrado_por);
        $this->assertSame('Salida olvidada, corregida según el parte manual del vigilante', $correccion->motivo);
    }

    public function test_sin_motivo_devuelve_422(): void
    {
        $tenant = Tenant::factory()->create();
        $admin = User::factory()->create(['tenant_id' => $tenant->id, 'rol' => UserRole::Admin, 'password' => bcrypt('secret123')]);
        $userMiembro = User::factory()->create(['tenant_id' => $tenant->id]);
        $miembro = MiembroEquipo::factory()->for($tenant)->create(['user_id' => $userMiembro->id]);
        $original = Fichaje::factory()->for($tenant)->create(['miembro_equipo_id' => $miembro->id]);

        $this->loginAs($admin);

        $response = $this->post("/fichajes/{$original->id}/corregir", [
            'tipo' => 'salida',
            'ocurrido_at' => now()->toDateTimeString(),
        ]);

        $response->assertSessionHasErrors('motivo');
        $this->assertDatabaseCount('fichajes', 1);
    }

    public function test_usuario_sin_permiso_recibe_403(): void
    {
        $tenant = Tenant::factory()->create();
        $usuario = User::factory()->create(['tenant_id' => $tenant->id, 'rol' => UserRole::Usuario, 'password' => bcrypt('secret123')]);
        $miembro = MiembroEquipo::factory()->for($tenant)->create(['user_id' => $usuario->id]);
        $original = Fichaje::factory()->for($tenant)->create(['miembro_equipo_id' => $miembro->id]);

        $this->loginAs($usuario);

        $response = $this->post("/fichajes/{$original->id}/corregir", [
            'tipo' => 'salida',
            'ocurrido_at' => now()->toDateTimeString(),
            'motivo' => 'Intento no autorizado',
        ]);

        $response->assertForbidden();
    }

    public function test_el_informe_refleja_el_valor_corregido(): void
    {
        $tenant = Tenant::factory()->create();
        $admin = User::factory()->create(['tenant_id' => $tenant->id, 'rol' => UserRole::Admin, 'password' => bcrypt('secret123')]);
        $userMiembro = User::factory()->create(['tenant_id' => $tenant->id]);
        $miembro = MiembroEquipo::factory()->for($tenant)->create(['user_id' => $userMiembro->id]);

        $dia = now()->startOfDay();
        Fichaje::factory()->for($tenant)->create(['miembro_equipo_id' => $miembro->id, 'tipo' => TipoEventoFichaje::Entrada, 'ocurrido_at' => $dia->copy()->setTime(9, 0)]);
        $salidaOriginal = Fichaje::factory()->for($tenant)->create(['miembro_equipo_id' => $miembro->id, 'tipo' => TipoEventoFichaje::Salida, 'ocurrido_at' => $dia->copy()->setTime(16, 0)]);

        $this->loginAs($admin);

        // Corrige la salida de 16:00 UTC a 17:00 UTC (jornada real de 8h, no 7h). El formulario
        // envía la hora en la zona local del tenant, así que se expresa el instante objetivo en
        // esa zona (robusto a DST) — el controller lo reconvierte a UTC al guardar.
        $salidaCorregidaLocal = $dia->copy()->setTime(17, 0)
            ->setTimezone(ConfigTenant::DEFAULT_ZONA_HORARIA)
            ->toDateTimeString();

        $this->post("/fichajes/{$salidaOriginal->id}/corregir", [
            'tipo' => 'salida',
            'ocurrido_at' => $salidaCorregidaLocal,
            'motivo' => 'El reloj del centro marcaba mal la hora de salida',
        ]);

        $datos = app(InformeJornada::class)->generar($miembro, $dia->copy(), $dia->copy()->addDay());

        $this->assertEqualsWithDelta(8.0, $datos['total_horas'], 0.01);
        $this->assertCount(3, $datos['eventos']);
    }
}
