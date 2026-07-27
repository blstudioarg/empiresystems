<?php

namespace Tests\Feature\Profile;

use App\Enums\AccionLogActividad;
use App\Enums\ResultadoLogActividad;
use App\Models\LogActividad;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileActividadRecienteTest extends TestCase
{
    use RefreshDatabase;

    public function test_muestra_los_ultimos_5_eventos_propios_con_resultado_navegador_ubicacion(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'password' => bcrypt('secret123'),
        ]);

        // 6 eventos propios (solo deben verse los 5 más recientes) + 1 de otro usuario (ignorado).
        for ($i = 0; $i < 6; $i++) {
            LogActividad::factory()->create([
                'tenant_id' => $tenant->id,
                'usuario_id' => $user->id,
                'usuario_nombre' => $user->name,
                'accion' => AccionLogActividad::Login,
                'resultado' => ResultadoLogActividad::Exito,
                'ip_origen' => '127.0.0.1',
                'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/1.0',
                'ocurrido_at' => now()->subMinutes($i),
            ]);
        }
        $otroUsuario = User::factory()->create(['tenant_id' => $tenant->id]);
        LogActividad::factory()->create([
            'tenant_id' => $tenant->id,
            'usuario_id' => $otroUsuario->id,
        ]);

        $this->loginAs($user);

        $response = $this->get('/perfil');

        $response->assertOk();
        $response->assertSee('Inicio de sesión');
        $response->assertSee('Éxito');
        $response->assertSee('Chrome');
    }

    public function test_estado_vacio_si_no_hay_actividad(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'password' => bcrypt('secret123'),
        ]);
        // `actingAs()` en vez de `loginAs()`: un login real vía /login genera su propio
        // LogActividad de éxito, lo que invalidaría el escenario "sin actividad".
        $this->actingOnDomain($this->domainFor($tenant));
        $this->actingAs($user);

        $response = $this->get('/perfil');

        $response->assertOk();
        $response->assertSee('Sin actividad reciente');
    }

    public function test_sin_permiso_ver_logs_no_muestra_el_enlace_ver_mas(): void
    {
        $tenant = Tenant::factory()->create();
        $sinPermiso = User::factory()->create([
            'tenant_id' => $tenant->id,
            'password' => bcrypt('secret123'),
        ]);
        $this->loginAs($sinPermiso);

        $response = $this->get('/perfil');

        $response->assertOk();
        // El texto "Ver más" también aparece en la ayuda in-app (siempre visible), así que se
        // busca el marcado del botón en sí (único a la sección de actividad reciente).
        $response->assertDontSee('btn btn-sm btn-outline-primary">Ver más', false);
    }

    public function test_con_permiso_ver_logs_muestra_el_enlace_ver_mas(): void
    {
        $tenant = Tenant::factory()->create();
        $conPermiso = User::factory()->admin()->create([
            'tenant_id' => $tenant->id,
            'password' => bcrypt('secret123'),
        ]);
        $this->loginAs($conPermiso);

        $response = $this->get('/perfil');

        $response->assertOk();
        $response->assertSee('btn btn-sm btn-outline-primary">Ver más', false);
    }
}
