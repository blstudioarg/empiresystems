<?php

namespace Tests\Feature;

use App\Models\Fichaje;
use App\Models\MiembroEquipo;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FichajeTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_fichaje_siempre_queda_asociado_al_tenant_del_miembro_que_ficha(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $userA = User::factory()->create(['tenant_id' => $tenantA->id, 'password' => bcrypt('secret123')]);
        MiembroEquipo::factory()->for($tenantA)->create(['user_id' => $userA->id]);

        $this->loginAs($userA);
        $this->post('/fichajes', ['tipo' => 'entrada']);

        $fichaje = Fichaje::first();
        $this->assertSame($tenantA->id, $fichaje->tenant_id);
        $this->assertNotSame($tenantB->id, $fichaje->tenant_id);
    }

    public function test_un_miembro_nunca_ve_fichajes_de_otro_tenant(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $userA = User::factory()->create(['tenant_id' => $tenantA->id, 'password' => bcrypt('secret123')]);
        $miembroA = MiembroEquipo::factory()->for($tenantA)->create(['user_id' => $userA->id]);

        Fichaje::factory()->for($tenantB)->create([
            'miembro_equipo_id' => MiembroEquipo::factory()->for($tenantB)->create()->id,
        ]);
        Fichaje::factory()->for($tenantA)->create(['miembro_equipo_id' => $miembroA->id]);

        $this->loginAs($userA);

        $response = $this->get('/fichajes');

        $response->assertOk();
        $visibles = Fichaje::all();
        $this->assertCount(1, $visibles);
        $this->assertSame($tenantA->id, $visibles->first()->tenant_id);
    }

    public function test_el_veredicto_de_un_fichaje_nunca_se_calcula_contra_un_miembro_de_otro_tenant(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $userA = User::factory()->create(['tenant_id' => $tenantA->id, 'password' => bcrypt('secret123')]);

        // Miembro del tenant A lejos de su trabajo; un miembro homónimo del tenant B (mismo
        // user_id no aplica por unique, pero simula datos de otro tenant) está cerca de esas
        // coordenadas — no debe "tomar prestada" esa ubicación.
        MiembroEquipo::factory()->for($tenantA)->create([
            'user_id' => $userA->id,
            'trabajo_latitud' => 0.0,
            'trabajo_longitud' => 0.0,
            'distancia_max_metros' => 100,
        ]);
        MiembroEquipo::factory()->for($tenantB)->create([
            'trabajo_latitud' => 40.4168,
            'trabajo_longitud' => -3.7038,
            'distancia_max_metros' => 100,
        ]);

        $this->loginAs($userA);
        $this->post('/fichajes', ['tipo' => 'entrada', 'latitud' => 40.4168, 'longitud' => -3.7038]);

        $fichaje = Fichaje::first();
        $this->assertSame('fuera', $fichaje->resultado_ubicacion->value);
    }
}
