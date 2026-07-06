<?php

namespace Tests\Feature;

use App\Models\MiembroEquipo;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class FichajeRegistroTest extends TestCase
{
    use RefreshDatabase;

    public function test_entrada_crea_evento_con_hora_de_servidor_ignorando_la_del_cliente(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $miembro = MiembroEquipo::factory()->for($tenant)->create([
            'user_id' => $user->id,
            'trabajo_latitud' => 40.4168,
            'trabajo_longitud' => -3.7038,
            'distancia_max_metros' => 100,
        ]);
        $this->loginAs($user);

        $antes = now();

        $response = $this->post('/fichajes', [
            'tipo' => 'entrada',
            'latitud' => 40.4168,
            'longitud' => -3.7038,
            'precision' => 10,
            // Hora manipulada del cliente: debe ser ignorada por completo.
            'ocurrido_at' => now()->subYears(3)->toDateTimeString(),
        ]);

        $response->assertRedirect('/fichajes');
        $this->assertDatabaseCount('fichajes', 1);

        $fichaje = \App\Models\Fichaje::first();
        // La columna es DATETIME (sin microsegundos): tolerancia de 1s para el truncado de
        // fracción de segundo entre $antes y el guardado real.
        $this->assertTrue($fichaje->ocurrido_at->between($antes->copy()->subSecond(), now()->addSecond()));
        $this->assertSame('entrada', $fichaje->tipo->value);
        $this->assertSame('dentro', $fichaje->resultado_ubicacion->value);
    }

    public function test_salida_cierra_jornada_tras_una_entrada(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $miembro = MiembroEquipo::factory()->for($tenant)->create(['user_id' => $user->id]);
        $this->loginAs($user);

        $this->post('/fichajes', ['tipo' => 'entrada']);
        $response = $this->post('/fichajes', ['tipo' => 'salida']);

        $response->assertRedirect('/fichajes');
        $this->assertDatabaseCount('fichajes', 2);
        $this->assertSame('salida', \App\Models\Fichaje::latest('id')->first()->tipo->value);
    }

    public function test_no_existe_ruta_de_edicion_ni_borrado_de_fichajes(): void
    {
        $this->assertFalse(Route::has('fichajes.update'));
        $this->assertFalse(Route::has('fichajes.destroy'));
        $this->assertFalse(Route::has('fichajes.edit'));
    }

    public function test_sin_permiso_de_geolocalizacion_se_marca_sin_ubicacion(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        MiembroEquipo::factory()->for($tenant)->create(['user_id' => $user->id]);
        $this->loginAs($user);

        $response = $this->post('/fichajes', ['tipo' => 'entrada']);

        $response->assertRedirect('/fichajes');
        $fichaje = \App\Models\Fichaje::first();
        $this->assertSame('sin_ubicacion', $fichaje->resultado_ubicacion->value);
        $this->assertNull($fichaje->distancia_metros);
    }

    public function test_usuario_sin_perfil_de_miembro_no_puede_fichar(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $this->loginAs($user);

        $response = $this->post('/fichajes', ['tipo' => 'entrada']);

        $response->assertForbidden();
        $this->assertDatabaseCount('fichajes', 0);
    }
}
