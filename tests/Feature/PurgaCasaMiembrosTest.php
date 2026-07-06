<?php

namespace Tests\Feature;

use App\Models\Configuracion;
use App\Models\Fichaje;
use App\Models\MiembroEquipo;
use App\Models\Tenant;
use App\Models\User;
use App\Support\RetencionMiembroTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class PurgaCasaMiembrosTest extends TestCase
{
    use RefreshDatabase;

    public function test_purga_nulifica_datos_de_casa_de_miembros_dados_de_baja_conservando_el_resto(): void
    {
        $tenant = Tenant::factory()->create();
        Configuracion::factory()->for($tenant)->create(['clave' => RetencionMiembroTenant::CLAVE, 'valor' => '30']);

        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $miembro = MiembroEquipo::factory()->for($tenant)->create([
            'user_id' => $user->id,
            'casa_direccion' => 'Calle Falsa 123',
            'casa_latitud' => 40.42,
            'casa_longitud' => -3.70,
            'distancia_casa_trabajo_metros' => 1500,
            'activo' => false,
            'dado_baja_at' => now()->subDays(40),
        ]);
        $miembro->delete(); // baja = softDelete + dado_baja_at (patrón del destroy() real)

        Fichaje::factory()->for($tenant)->create(['miembro_equipo_id' => $miembro->id]);

        Artisan::call('miembros:purgar-casa');

        $miembro->refresh();
        $this->assertNull($miembro->casa_direccion);
        $this->assertNull($miembro->casa_latitud);
        $this->assertNull($miembro->casa_longitud);
        // La métrica derivada (no identificable de posición) y sus fichajes se conservan.
        $this->assertSame(1500, $miembro->distancia_casa_trabajo_metros);
        $this->assertDatabaseCount('fichajes', 1);
    }

    public function test_miembro_dado_de_baja_recientemente_no_se_purga_todavia(): void
    {
        $tenant = Tenant::factory()->create();
        Configuracion::factory()->for($tenant)->create(['clave' => RetencionMiembroTenant::CLAVE, 'valor' => '30']);

        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $miembro = MiembroEquipo::factory()->for($tenant)->create([
            'user_id' => $user->id,
            'casa_direccion' => 'Calle Falsa 123',
            'casa_latitud' => 40.42,
            'casa_longitud' => -3.70,
            'activo' => false,
            'dado_baja_at' => now()->subDays(5),
        ]);
        $miembro->delete();

        Artisan::call('miembros:purgar-casa');

        $miembro->refresh();
        $this->assertNotNull($miembro->casa_direccion);
        $this->assertNotNull($miembro->casa_latitud);
    }

    public function test_miembro_activo_sin_baja_nunca_se_purga(): void
    {
        $tenant = Tenant::factory()->create();
        Configuracion::factory()->for($tenant)->create(['clave' => RetencionMiembroTenant::CLAVE, 'valor' => '30']);

        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $miembro = MiembroEquipo::factory()->for($tenant)->create([
            'user_id' => $user->id,
            'casa_direccion' => 'Calle Falsa 123',
            'activo' => true,
            'dado_baja_at' => null,
        ]);

        Artisan::call('miembros:purgar-casa');

        $miembro->refresh();
        $this->assertNotNull($miembro->casa_direccion);
    }
}
