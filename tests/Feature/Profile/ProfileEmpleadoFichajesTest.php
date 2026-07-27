<?php

namespace Tests\Feature\Profile;

use App\Enums\TipoEventoFichaje;
use App\Models\Fichaje;
use App\Models\MiembroEquipo;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileEmpleadoFichajesTest extends TestCase
{
    use RefreshDatabase;

    public function test_muestra_puesto_centro_y_estado_de_fichaje_con_vinculo_activo(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'password' => bcrypt('secret123'),
        ]);
        $miembro = MiembroEquipo::factory()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'puesto' => 'Repartidor',
            'trabajo_direccion' => 'Calle Falsa 123',
            'activo' => true,
            'dado_baja_at' => null,
        ]);
        Fichaje::factory()->create([
            'tenant_id' => $tenant->id,
            'miembro_equipo_id' => $miembro->id,
            'tipo' => TipoEventoFichaje::Entrada,
        ]);

        $this->loginAs($user);

        $response = $this->get('/perfil');

        $response->assertOk();
        $response->assertSee('Repartidor');
        $response->assertSee('Calle Falsa 123');
        $response->assertSee('Jornada abierta');

        // Comparar contra el estado que muestra /fichajes (misma fuente de verdad).
        $fichajesResponse = $this->get('/fichajes');
        $fichajesResponse->assertOk();
        $fichajesResponse->assertSee('abierta');
    }

    public function test_seccion_omitida_sin_vinculo_de_miembro_equipo(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'password' => bcrypt('secret123'),
        ]);
        $this->loginAs($user);

        $response = $this->get('/perfil');

        $response->assertOk();
        // El texto "Mi puesto y fichaje" también aparece en la ayuda in-app (siempre visible),
        // así que se busca el botón de la pestaña en sí (marcado único, condicional al vínculo).
        $response->assertDontSee('id="tab-fichaje-btn"', false);
    }

    public function test_seccion_omitida_con_miembro_dado_de_baja(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'password' => bcrypt('secret123'),
        ]);
        MiembroEquipo::factory()->inactivo()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
        ]);
        $this->loginAs($user);

        $response = $this->get('/perfil');

        $response->assertOk();
        // El texto "Mi puesto y fichaje" también aparece en la ayuda in-app (siempre visible),
        // así que se busca el botón de la pestaña en sí (marcado único, condicional al vínculo).
        $response->assertDontSee('id="tab-fichaje-btn"', false);
    }
}
