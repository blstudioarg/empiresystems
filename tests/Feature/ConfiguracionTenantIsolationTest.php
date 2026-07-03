<?php

namespace Tests\Feature;

use App\Models\Configuracion;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ConfiguracionTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private function loginAs(User $user): void
    {
        $this->post('/login', [
            'email' => $user->email,
            'password' => 'secret123',
        ]);
    }

    public function test_guardar_colores_como_a_no_afecta_configuraciones_de_b(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $userA = User::factory()->create([
            'tenant_id' => $tenantA->id,
            'password' => bcrypt('secret123'),
        ]);

        Configuracion::factory()->create([
            'tenant_id' => $tenantB->id,
            'clave' => 'apariencia.color_primario',
            'valor' => '#000000',
        ]);

        $this->loginAs($userA);

        $this->put('/configuracion/apariencia', [
            'color_primario' => '#ABCDEF',
        ]);

        $this->assertDatabaseHas('configuraciones', [
            'tenant_id' => $tenantA->id,
            'clave' => 'apariencia.color_primario',
            'valor' => '#ABCDEF',
        ]);

        $this->assertDatabaseHas('configuraciones', [
            'tenant_id' => $tenantB->id,
            'clave' => 'apariencia.color_primario',
            'valor' => '#000000',
        ]);
    }

    public function test_cargar_configuracion_como_a_no_muestra_valores_de_b(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $userA = User::factory()->create([
            'tenant_id' => $tenantA->id,
            'password' => bcrypt('secret123'),
        ]);

        Configuracion::factory()->create([
            'tenant_id' => $tenantB->id,
            'clave' => 'apariencia.color_primario',
            'valor' => '#123123',
        ]);

        $this->loginAs($userA);

        $response = $this->get('/configuracion');

        $response->assertOk();
        $response->assertDontSee('#123123');
    }

    public function test_logo_subido_por_a_no_afecta_logo_path_de_b(): void
    {
        Storage::fake('public');

        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create(['logo_path' => null]);

        $userA = User::factory()->create([
            'tenant_id' => $tenantA->id,
            'password' => bcrypt('secret123'),
        ]);

        $this->loginAs($userA);

        $logo = UploadedFile::fake()->image('logo.png', 100, 100)->size(200);

        $this->put('/configuracion/apariencia', [
            'logo' => $logo,
        ]);

        $tenantB->refresh();
        $this->assertNull($tenantB->logo_path);
    }
}
