<?php

namespace Tests\Feature;

use App\Models\Configuracion;
use App\Models\Fichaje;
use App\Models\MiembroEquipo;
use App\Models\Tenant;
use App\Models\User;
use App\Support\ConfigFichajes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FichajeGeofencingTest extends TestCase
{
    use RefreshDatabase;

    private function crearMiembro(Tenant $tenant, User $user, int $distanciaMax = 100): MiembroEquipo
    {
        return MiembroEquipo::factory()->for($tenant)->create([
            'user_id' => $user->id,
            'trabajo_latitud' => 40.4168,
            'trabajo_longitud' => -3.7038,
            'distancia_max_metros' => $distanciaMax,
        ]);
    }

    public function test_dentro_de_la_distancia_maxima_se_marca_dentro(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $this->crearMiembro($tenant, $user, 100);
        $this->loginAs($user);

        // A ~11 m del centro (0.0001 grados de latitud ≈ 11.1 m).
        $this->post('/fichajes', ['tipo' => 'entrada', 'latitud' => 40.4169, 'longitud' => -3.7038]);

        $fichaje = Fichaje::first();
        $this->assertSame('dentro', $fichaje->resultado_ubicacion->value);
        $this->assertLessThanOrEqual(100, $fichaje->distancia_metros);
    }

    public function test_justo_en_el_borde_se_marca_dentro(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        // Distancia máxima exactamente igual a la calculada por Haversine (borde inclusivo).
        $miembro = $this->crearMiembro($tenant, $user, 1000000);
        $distanciaExacta = \App\Support\Haversine::metros(40.4168, -3.7038, 40.42, -3.7038);
        $miembro->update(['distancia_max_metros' => $distanciaExacta]);
        $this->loginAs($user);

        $this->post('/fichajes', ['tipo' => 'entrada', 'latitud' => 40.42, 'longitud' => -3.7038]);

        $fichaje = Fichaje::first();
        $this->assertSame('dentro', $fichaje->resultado_ubicacion->value);
    }

    public function test_fuera_de_la_distancia_maxima_se_marca_fuera_y_no_se_pierde(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $this->crearMiembro($tenant, $user, 100);
        $this->loginAs($user);

        // ~1.1 km del centro de trabajo.
        $response = $this->post('/fichajes', ['tipo' => 'entrada', 'latitud' => 40.4268, 'longitud' => -3.7038]);

        $response->assertRedirect('/fichajes');
        $fichaje = Fichaje::first();
        $this->assertSame('fuera', $fichaje->resultado_ubicacion->value);
        $this->assertGreaterThan(100, $fichaje->distancia_metros);
    }

    public function test_sin_ubicacion_de_trabajo_configurada_se_marca_sin_ubicacion(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        MiembroEquipo::factory()->for($tenant)->create([
            'user_id' => $user->id,
            'trabajo_latitud' => null,
            'trabajo_longitud' => null,
        ]);
        $this->loginAs($user);

        $this->post('/fichajes', ['tipo' => 'entrada', 'latitud' => 40.4168, 'longitud' => -3.7038]);

        $fichaje = Fichaje::first();
        $this->assertSame('sin_ubicacion', $fichaje->resultado_ubicacion->value);
    }

    public function test_geofencing_bloqueante_rechaza_el_fichaje_fuera_sin_persistir(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $this->crearMiembro($tenant, $user, 100);
        Configuracion::factory()->for($tenant)->create([
            'clave' => ConfigFichajes::CLAVE_GEOFENCING_BLOQUEANTE,
            'valor' => '1',
        ]);
        $this->loginAs($user);

        $response = $this->post('/fichajes', ['tipo' => 'entrada', 'latitud' => 40.4268, 'longitud' => -3.7038]);

        $response->assertStatus(422);
        $this->assertDatabaseCount('fichajes', 0);
    }
}
