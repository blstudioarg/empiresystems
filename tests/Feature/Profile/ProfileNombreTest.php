<?php

namespace Tests\Feature\Profile;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileNombreTest extends TestCase
{
    use RefreshDatabase;

    public function test_editar_nombre_se_aplica_de_inmediato_y_sin_pedir_contrasena(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Nombre Viejo',
            'password' => bcrypt('secret123'),
        ]);
        $this->loginAs($user);

        $response = $this->putJson('/perfil/nombre', ['name' => 'Nombre Nuevo']);

        $response->assertOk();
        $this->assertSame('Nombre Nuevo', $user->fresh()->name);

        $verPerfil = $this->get('/perfil');
        $verPerfil->assertSee('Nombre Nuevo');
    }

    public function test_error_si_el_nombre_esta_vacio(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'password' => bcrypt('secret123'),
        ]);
        $this->loginAs($user);

        $response = $this->putJson('/perfil/nombre', ['name' => '']);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('name');
    }
}
