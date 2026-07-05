<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArchivosVistaTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_vista_del_explorador_renderiza_correctamente(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);

        $this->loginAs($user);

        $response = $this->get('/archivos');

        $response->assertOk();
        $response->assertSee('Archivos');
        $response->assertSee('archivos-explorer', false);
    }
}
