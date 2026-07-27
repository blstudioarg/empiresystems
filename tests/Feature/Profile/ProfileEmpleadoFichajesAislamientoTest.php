<?php

namespace Tests\Feature\Profile;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\ConTenantSecundario;
use Tests\TestCase;

class ProfileEmpleadoFichajesAislamientoTest extends TestCase
{
    use ConTenantSecundario, RefreshDatabase;

    public function test_fichajes_y_miembro_de_otro_tenant_nunca_aparecen_en_el_perfil(): void
    {
        $this->crearTenantSecundario();

        $tenant = Tenant::factory()->create();
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'password' => bcrypt('secret123'),
        ]);

        $this->loginAs($user);

        $response = $this->get('/perfil');

        $response->assertOk();
        // El usuario del tenant primario no tiene vínculo de miembro de equipo propio, así que la
        // sección no debe aparecer, y en particular no debe filtrarse ningún dato del tenant B.
        $response->assertDontSee('id="tab-fichaje-btn"', false);
        $response->assertDontSee($this->miembroEquipoSecundario->puesto);
    }
}
