<?php

namespace Tests\Feature\Profile;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\ConTenantSecundario;
use Tests\TestCase;

class ProfileActividadRecienteAislamientoTest extends TestCase
{
    use ConTenantSecundario, RefreshDatabase;

    public function test_un_usuario_nunca_ve_actividad_de_un_usuario_de_otro_tenant(): void
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
        $response->assertDontSee($this->logActividadSecundario->descripcion);
        $response->assertDontSee($this->usuarioSecundario->name);
    }
}
